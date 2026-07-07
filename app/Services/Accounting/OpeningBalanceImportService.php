<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountingImportBatchStatus;
use App\Exceptions\ImportExport\ImportRowException;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\ImportExportJob;
use App\Models\OpeningBalanceImportBatch;
use App\Models\OpeningBalanceImportLine;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ImportExport\ImportContext;
use Illuminate\Support\Facades\DB;

final class OpeningBalanceImportService
{
    /** @var list<array<string, mixed>> */
    private array $pendingLines = [];

    private float $totalDebits = 0.0;

    private float $totalCredits = 0.0;

    public function __construct(
        private readonly JournalService $journalService,
        private readonly FinancialSettingsService $settings,
    ) {}

    public function reset(): void
    {
        $this->pendingLines = [];
        $this->totalDebits = 0.0;
        $this->totalCredits = 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function addLine(array $row): void
    {
        $accountCode = strtoupper(trim((string) ($row['account_code'] ?? '')));
        $debit = round((float) ($row['debit'] ?? 0), 2);
        $credit = round((float) ($row['credit'] ?? 0), 2);

        if ($accountCode === '') {
            throw ImportRowException::fromValidationErrors([
                'account_code' => ['Account code is required.'],
            ]);
        }

        if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
            throw ImportRowException::fromValidationErrors([
                'debit' => ['Each line must have either a debit or a credit amount.'],
            ]);
        }

        $account = ChartOfAccount::query()->where('code', $accountCode)->first();

        if ($account === null) {
            throw ImportRowException::fromValidationErrors([
                'account_code' => ["Account not found: {$accountCode}"],
            ]);
        }

        if (! $account->is_postable) {
            throw ImportRowException::fromValidationErrors([
                'account_code' => ["Account {$accountCode} is not postable."],
            ]);
        }

        [$partyType, $partyId] = $this->resolveParty($row);

        $warehouseId = null;
        if (! empty($row['warehouse_code'])) {
            $warehouse = Warehouse::query()
                ->where('code', strtoupper(trim((string) $row['warehouse_code'])))
                ->first();

            if ($warehouse === null) {
                throw ImportRowException::fromValidationErrors([
                    'warehouse_code' => ['Warehouse not found.'],
                ]);
            }

            $warehouseId = $warehouse->id;
        }

        $this->pendingLines[] = [
            'account_id' => $account->id,
            'debit' => $debit,
            'credit' => $credit,
            'party_type' => $partyType,
            'party_id' => $partyId,
            'warehouse_id' => $warehouseId,
            'description' => trim((string) ($row['description'] ?? '')) ?: null,
        ];

        $this->totalDebits += $debit;
        $this->totalCredits += $credit;
    }

    public function finalize(ImportContext $context): void
    {
        if ($context->isDryRun || $this->pendingLines === []) {
            $this->reset();

            return;
        }

        $job = ImportExportJob::query()->findOrFail($context->jobId);
        $cutoverDate = (string) ($context->options['cutover_date'] ?? $this->settings->get()->accounting_cutover_date?->toDateString() ?? now()->toDateString());
        $difference = round($this->totalDebits - $this->totalCredits, 2);
        $isBalanced = abs($difference) < 0.01;

        DB::transaction(function () use ($context, $job, $cutoverDate, $difference, $isBalanced) {
            $batch = OpeningBalanceImportBatch::query()->create([
                'cutover_date' => $cutoverDate,
                'file_name' => (string) ($job->original_filename ?? 'opening-balances.csv'),
                'imported_by' => $context->userId,
                'status' => $isBalanced ? AccountingImportBatchStatus::Validated : AccountingImportBatchStatus::Failed,
                'validation_summary' => [
                    'total_debits' => $this->totalDebits,
                    'total_credits' => $this->totalCredits,
                    'difference' => $difference,
                    'line_count' => count($this->pendingLines),
                    'balanced' => $isBalanced,
                ],
            ]);

            foreach ($this->pendingLines as $line) {
                OpeningBalanceImportLine::query()->create([
                    'opening_balance_import_batch_id' => $batch->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'party_type' => $line['party_type'],
                    'party_id' => $line['party_id'],
                    'warehouse_id' => $line['warehouse_id'],
                    'validation_status' => $isBalanced ? 'valid' : 'invalid',
                    'validation_message' => $isBalanced ? null : 'Batch is out of balance.',
                ]);
            }

            if (! $isBalanced) {
                return;
            }

            $journalLines = array_map(fn (array $line) => [
                'account_id' => $line['account_id'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'warehouse_id' => $line['warehouse_id'],
                'party_type' => $line['party_type'],
                'party_id' => $line['party_id'],
                'description' => $line['description'],
            ], $this->pendingLines);

            $entry = $this->journalService->createDraft([
                'journal_date' => $cutoverDate,
                'description' => 'Opening balance import',
                'source_module' => 'accounting',
                'source_event' => 'opening_balance.imported',
                'source_reference_type' => OpeningBalanceImportBatch::class,
                'source_reference_id' => $batch->id,
                'is_system_generated' => true,
                'is_opening_balance' => true,
            ], $journalLines, $context->userId);

            $entry = $this->journalService->post($entry, $context->userId);

            $batch->update([
                'status' => AccountingImportBatchStatus::Completed,
                'posted_journal_entry_id' => $entry->id,
            ]);
        });

        $this->reset();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveParty(array $row): array
    {
        $partyType = strtolower(trim((string) ($row['party_type'] ?? '')));
        $partyReference = trim((string) ($row['party_reference'] ?? ''));

        if ($partyType === '' || $partyReference === '') {
            return [null, null];
        }

        return match ($partyType) {
            'customer' => $this->resolveCustomerParty($partyReference),
            'supplier' => $this->resolveSupplierParty($partyReference),
            default => throw ImportRowException::fromValidationErrors([
                'party_type' => ['Party type must be customer or supplier.'],
            ]),
        };
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveCustomerParty(string $reference): array
    {
        $customer = Customer::query()
            ->where('email', $reference)
            ->orWhere('phone', $reference)
            ->orWhere('name', $reference)
            ->first();

        if ($customer === null) {
            throw ImportRowException::fromValidationErrors([
                'party_reference' => ['Customer not found.'],
            ]);
        }

        return [Customer::class, $customer->id];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveSupplierParty(string $reference): array
    {
        $supplier = Supplier::query()->where('code', $reference)->first();

        if ($supplier === null) {
            throw ImportRowException::fromValidationErrors([
                'party_reference' => ['Supplier not found.'],
            ]);
        }

        return [Supplier::class, $supplier->id];
    }
}
