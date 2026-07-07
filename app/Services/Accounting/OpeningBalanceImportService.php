<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountingImportBatchStatus;
use App\Enums\OpeningBalanceBatchType;
use App\Enums\OpeningBalanceReconciliationStatus;
use App\Exceptions\ImportExport\ImportRowException;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\ImportExportJob;
use App\Models\OpeningBalanceImportBatch;
use App\Models\OpeningBalanceImportLine;
use App\Models\OpeningBalanceReconciliation;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ImportExport\ImportContext;
use DomainException;
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
            'account_code' => $accountCode,
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
        $batchType = OpeningBalanceBatchType::tryFrom((string) ($context->options['batch_type'] ?? 'full_gl'))
            ?? OpeningBalanceBatchType::FullGl;
        $difference = round($this->totalDebits - $this->totalCredits, 2);
        $isBalanced = abs($difference) < 0.01;

        DB::transaction(function () use ($context, $job, $cutoverDate, $batchType, $difference, $isBalanced) {
            $batch = OpeningBalanceImportBatch::query()->create([
                'cutover_date' => $cutoverDate,
                'file_name' => (string) ($job->original_filename ?? 'opening-balances.csv'),
                'batch_type' => $batchType,
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

            if ($isBalanced) {
                $this->runReconciliationChecks($batch);
            }
        });

        $this->reset();
    }

    public function approveBatch(
        OpeningBalanceImportBatch $batch,
        int $userId,
        ?float $varianceTolerance = null,
    ): OpeningBalanceImportBatch {
        if ($batch->status !== AccountingImportBatchStatus::Validated) {
            throw new DomainException('Only validated opening balance batches can be approved.');
        }

        $tolerance = $varianceTolerance ?? 0.01;
        $batch->loadMissing(['lines', 'reconciliations']);

        $unreconciled = $batch->reconciliations
            ->filter(fn (OpeningBalanceReconciliation $reconciliation) => $reconciliation->status === OpeningBalanceReconciliationStatus::Unreconciled
                && abs((float) $reconciliation->variance) > $tolerance);

        if ($unreconciled->isNotEmpty()) {
            throw new DomainException('Opening balance reconciliation variances must be approved before posting.');
        }

        return DB::transaction(function () use ($batch, $userId) {
            $journalLines = $batch->lines->map(fn (OpeningBalanceImportLine $line) => [
                'account_id' => $line->account_id,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'warehouse_id' => $line->warehouse_id,
                'party_type' => $line->party_type,
                'party_id' => $line->party_id,
            ])->all();

            $entry = $this->journalService->createDraft([
                'journal_date' => $batch->cutover_date->toDateString(),
                'description' => 'Opening balance import',
                'source_module' => 'accounting',
                'source_event' => 'opening_balance.imported',
                'source_reference_type' => OpeningBalanceImportBatch::class,
                'source_reference_id' => $batch->id,
                'is_system_generated' => true,
                'is_opening_balance' => true,
            ], $journalLines, $userId);

            $entry = $this->journalService->post($entry, $userId);

            $batch->update([
                'status' => AccountingImportBatchStatus::Completed,
                'approved_by' => $userId,
                'approved_at' => now(),
                'imported_at' => now(),
                'posted_journal_entry_id' => $entry->id,
            ]);

            return $batch->fresh(['lines', 'reconciliations', 'postedJournalEntry']);
        });
    }

    public function approveVariance(OpeningBalanceReconciliation $reconciliation, int $userId): OpeningBalanceReconciliation
    {
        $reconciliation->update([
            'status' => OpeningBalanceReconciliationStatus::Reconciled,
            'variance_approved_by' => $userId,
        ]);

        return $reconciliation->fresh();
    }

    private function runReconciliationChecks(OpeningBalanceImportBatch $batch): void
    {
        if ($batch->batch_type === OpeningBalanceBatchType::ArAging) {
            $this->reconcileArAging($batch);
        }

        if ($batch->batch_type === OpeningBalanceBatchType::ApAging) {
            $this->reconcileApAging($batch);
        }
    }

    private function reconcileArAging(OpeningBalanceImportBatch $batch): void
    {
        $importTotal = (float) OpeningBalanceImportLine::query()
            ->where('opening_balance_import_batch_id', $batch->id)
            ->where('party_type', Customer::class)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');

        $arAccountId = AccountMapping::query()
            ->where('mapping_key', 'accounts_receivable')
            ->where('status', 'active')
            ->value('account_id');

        $sourceTotal = $this->controlAccountBalance($batch, $arAccountId);
        $this->storeReconciliation($batch, 'ar_aging', $sourceTotal, $importTotal);
    }

    private function reconcileApAging(OpeningBalanceImportBatch $batch): void
    {
        $importTotal = (float) OpeningBalanceImportLine::query()
            ->where('opening_balance_import_batch_id', $batch->id)
            ->where('party_type', Supplier::class)
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')
            ->value('balance');

        $apAccountId = AccountMapping::query()
            ->where('mapping_key', 'accounts_payable')
            ->where('status', 'active')
            ->value('account_id');

        $sourceTotal = $this->controlAccountBalance($batch, $apAccountId ? (int) $apAccountId : null);
        $this->storeReconciliation($batch, 'ap_aging', $sourceTotal, $importTotal);
    }

    private function controlAccountBalance(OpeningBalanceImportBatch $batch, ?int $accountId): float
    {
        if ($accountId === null) {
            return 0.0;
        }

        return (float) OpeningBalanceImportLine::query()
            ->where('opening_balance_import_batch_id', $batch->id)
            ->where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');
    }

    private function storeReconciliation(
        OpeningBalanceImportBatch $batch,
        string $type,
        float $sourceTotal,
        float $importTotal,
    ): void {
        $variance = round($sourceTotal - $importTotal, 2);

        OpeningBalanceReconciliation::query()->create([
            'opening_balance_import_batch_id' => $batch->id,
            'reconciliation_type' => $type,
            'source_total' => $sourceTotal,
            'import_total' => $importTotal,
            'variance' => $variance,
            'status' => abs($variance) < 0.01
                ? OpeningBalanceReconciliationStatus::Reconciled
                : OpeningBalanceReconciliationStatus::Unreconciled,
        ]);
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
