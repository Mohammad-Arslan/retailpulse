<?php

declare(strict_types=1);

namespace App\Services\Expense;

use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\CurrencyConversionService;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Storage\FileStorageDiskRegistrar;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class ExpenseService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly ExpenseApprovalPolicyResolver $approvalPolicies,
        private readonly AccountingEventService $accountingEvents,
        private readonly CurrencyConversionService $currencyConversion,
        private readonly FileStorageDiskRegistrar $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId): Expense {
            $category = ExpenseCategory::query()->findOrFail((int) $data['expense_category_id']);

            if ($category->is_group) {
                throw new DomainException('Cannot Post Expenses Against A Group Category.');
            }

            $expense = Expense::query()->create([
                ...$data,
                'expense_number' => $this->documentNumbers->next(
                    'expense_voucher',
                    'EXP',
                    isset($data['branch_id']) ? (int) $data['branch_id'] : null,
                ),
                'tax_amount' => $data['tax_amount'] ?? '0',
                'status' => 'draft',
                'approval_required' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $expense->approval_required = $this->approvalPolicies->requiresApproval($expense);
            $expense->save();

            return $expense->fresh(['category', 'attachments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data, int $userId): Expense
    {
        if (! in_array($expense->status, ['draft', 'pending_approval'], true)) {
            throw new DomainException('Only Draft Or Pending Expenses Can Be Updated.');
        }

        return DB::transaction(function () use ($expense, $data, $userId): Expense {
            $expense->update([
                ...$data,
                'updated_by' => $userId,
            ]);

            $expense->approval_required = $this->approvalPolicies->requiresApproval($expense->fresh());
            $expense->save();

            return $expense->fresh(['category', 'attachments']);
        });
    }

    public function submitForApproval(Expense $expense, int $userId): Expense
    {
        $this->assertReceiptSatisfied($expense);

        if ($expense->status !== 'draft') {
            throw new DomainException('Only Draft Expenses Can Be Submitted.');
        }

        if (! $expense->approval_required) {
            return $this->approveAndPost($expense, $userId);
        }

        $expense->update([
            'status' => 'pending_approval',
            'updated_by' => $userId,
        ]);

        return $expense->fresh();
    }

    public function approve(Expense $expense, int $userId): Expense
    {
        if (! in_array($expense->status, ['draft', 'pending_approval'], true)) {
            throw new DomainException('Expense Is Not Awaiting Approval.');
        }

        if ($expense->status === 'draft' && $expense->approval_required) {
            throw new DomainException('Expense Requires Approval Before Posting.');
        }

        return $this->approveAndPost($expense, $userId);
    }

    public function attachReceipt(Expense $expense, UploadedFile $file, int $userId): ExpenseAttachment
    {
        $disk = $this->storage->diskNameFor('expense_attachments');
        $path = $file->store("expenses/{$expense->id}", $disk);

        return $expense->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $userId,
        ]);
    }

    private function approveAndPost(Expense $expense, int $userId): Expense
    {
        $this->assertReceiptSatisfied($expense);

        return DB::transaction(function () use ($expense, $userId): Expense {
            $expense->loadMissing('category');

            $fx = $this->currencyConversion->convertToFunctional(
                (float) $expense->amount,
                $expense->currency_code,
                $expense->exchange_rate !== null ? (float) $expense->exchange_rate : null,
                $expense->expense_date?->toDateString(),
            );

            $net = (string) $expense->amount;
            $tax = (string) ($expense->tax_amount ?? '0');
            $gross = bcadd($net, $tax, 4);
            $paid = $expense->payment_method !== null && $expense->payment_method !== '';

            $payload = [
                'date' => $expense->expense_date->toDateString(),
                'branch_id' => $expense->branch_id,
                'legal_entity_id' => $expense->legal_entity_id,
                'cost_centre_id' => $expense->cost_centre_id,
                'currency_code' => $expense->currency_code,
                'exchange_rate' => $fx['exchange_rate'],
                'net_amount' => (float) $net,
                'tax_amount' => (float) $tax,
                'gross_amount' => $paid ? 0.0 : (float) $gross,
                'settlement_amount' => $paid ? (float) $gross : 0.0,
                'tax_type_id' => $expense->tax_type_id,
                'tax_direction' => 'purchase',
                'payment_method' => $expense->payment_method,
                'expense_category_id' => $expense->expense_category_id,
                'expense_account_mapping_key' => $expense->category->resolvedAccountMappingKey(),
                'party_type' => $expense->vendor_party_type,
                'party_id' => $expense->vendor_party_id,
                'description' => $expense->description ?? "Expense {$expense->expense_number}",
                'source_module' => 'expenses',
                'source_number' => $expense->expense_number,
                'user_id' => $userId,
            ];

            if ($paid) {
                $payload['payments'] = [[
                    'method' => $expense->payment_method,
                    'amount' => (float) $gross,
                    'status' => 'completed',
                ]];
            }

            $existing = $this->accountingEvents->findForSource('expense.posted', Expense::class, $expense->id);
            if ($existing?->journal_entry_id) {
                $expense->update([
                    'status' => 'posted',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'functional_amount' => number_format($fx['functional_amount'], 4, '.', ''),
                    'exchange_rate' => $fx['exchange_rate'],
                    'accounting_event_id' => $existing->id,
                    'journal_entry_id' => $existing->journal_entry_id,
                    'updated_by' => $userId,
                ]);

                return $expense->fresh(['category', 'attachments']);
            }

            $event = $this->accountingEvents->process(
                'expense.posted',
                Expense::class,
                $expense->id,
                $payload,
                $userId,
            );

            $expense->update([
                'status' => 'posted',
                'approved_by' => $userId,
                'approved_at' => now(),
                'functional_amount' => number_format(
                    (float) bcadd(
                        (string) $fx['functional_amount'],
                        (string) ($this->currencyConversion->convertToFunctional(
                            (float) $tax,
                            $expense->currency_code,
                            $fx['exchange_rate'],
                            $expense->expense_date?->toDateString(),
                        )['functional_amount']),
                        4,
                    ),
                    4,
                    '.',
                    '',
                ),
                'exchange_rate' => $fx['exchange_rate'],
                'accounting_event_id' => $event->id,
                'journal_entry_id' => $event->journal_entry_id,
                'updated_by' => $userId,
            ]);

            return $expense->fresh(['category', 'attachments']);
        });
    }

    private function assertReceiptSatisfied(Expense $expense): void
    {
        $expense->loadMissing(['category', 'attachments']);

        if ($expense->category?->requires_receipt && $expense->attachments->isEmpty()) {
            throw new DomainException('This Expense Category Requires A Receipt Attachment.');
        }
    }
}
