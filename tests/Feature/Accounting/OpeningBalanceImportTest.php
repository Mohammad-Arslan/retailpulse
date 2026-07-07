<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\AccountingImportBatchStatus;
use App\Enums\FiscalYearStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\OpeningBalanceBatchType;
use App\Enums\OpeningBalanceReconciliationStatus;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\FinancialSetting;
use App\Models\FiscalYear;
use App\Models\ImportExportJob;
use App\Models\JournalEntry;
use App\Models\OpeningBalanceImportBatch;
use App\Models\OpeningBalanceImportLine;
use App\Models\OpeningBalanceReconciliation;
use App\Models\User;
use App\Services\Accounting\OpeningBalanceImportService;
use App\Services\ImportExport\ImportContext;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OpeningBalanceImportTest extends TestCase
{
    use RefreshDatabase;

    private ChartOfAccount $cash;

    private ChartOfAccount $equity;

    private ChartOfAccount $ar;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cash = ChartOfAccount::query()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset', 'is_postable' => true]);
        $this->equity = ChartOfAccount::query()->create(['code' => '3400', 'name' => 'Equity', 'type' => 'equity', 'is_postable' => true]);
        $this->ar = ChartOfAccount::query()->create(['code' => '1300', 'name' => 'AR', 'type' => 'asset', 'is_postable' => true]);

        AccountMapping::query()->create([
            'mapping_key' => 'accounts_receivable',
            'account_id' => $this->ar->id,
            'status' => 'active',
            'priority' => 100,
        ]);

        FinancialSetting::query()->create([
            'functional_currency_code' => 'USD',
            'fiscal_year_start_month' => 1,
            'accounting_cutover_date' => '2026-07-01',
        ]);

        FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Open,
        ]);

        $this->user = User::factory()->create(['is_active' => true]);
    }

    private function createImportJob(): int
    {
        return (int) ImportExportJob::query()->create([
            'tenant_id' => 0,
            'ulid' => (string) Str::ulid(),
            'user_id' => $this->user->id,
            'type' => 'import',
            'entity_type' => 'opening-balances',
            'status' => 'completed',
            'original_filename' => 'opening-balances.csv',
        ])->id;
    }

    public function test_balanced_import_creates_validated_batch_without_journal(): void
    {
        $service = app(OpeningBalanceImportService::class);
        $service->addLine(['account_code' => '1100', 'debit' => 100]);
        $service->addLine(['account_code' => '3400', 'credit' => 100]);

        $service->finalize(new ImportContext(
            jobId: $this->createImportJob(),
            tenantId: null,
            userId: $this->user->id,
            mode: 'create',
            isDryRun: false,
            filePath: '',
            disk: 'local',
            options: ['cutover_date' => '2026-07-01'],
        ));

        $batch = OpeningBalanceImportBatch::query()->first();
        $this->assertNotNull($batch);
        $this->assertSame(AccountingImportBatchStatus::Validated, $batch->status);
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_unbalanced_import_marks_batch_failed(): void
    {
        $service = app(OpeningBalanceImportService::class);
        $service->addLine(['account_code' => '1100', 'debit' => 100]);
        $service->addLine(['account_code' => '3400', 'credit' => 99.99]);

        $service->finalize(new ImportContext(
            jobId: $this->createImportJob(),
            tenantId: null,
            userId: $this->user->id,
            mode: 'create',
            isDryRun: false,
            filePath: '',
            disk: 'local',
            options: ['cutover_date' => '2026-07-01'],
        ));

        $batch = OpeningBalanceImportBatch::query()->first();
        $this->assertSame(AccountingImportBatchStatus::Failed, $batch?->status);
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_approve_batch_posts_opening_balance_journal(): void
    {
        $batch = OpeningBalanceImportBatch::query()->create([
            'cutover_date' => '2026-07-01',
            'file_name' => 'ob.csv',
            'batch_type' => OpeningBalanceBatchType::FullGl,
            'imported_by' => $this->user->id,
            'status' => AccountingImportBatchStatus::Validated,
            'validation_summary' => ['balanced' => true],
        ]);

        OpeningBalanceImportLine::query()->create([
            'opening_balance_import_batch_id' => $batch->id,
            'account_id' => $this->cash->id,
            'debit' => 100,
            'credit' => 0,
            'validation_status' => 'valid',
        ]);

        OpeningBalanceImportLine::query()->create([
            'opening_balance_import_batch_id' => $batch->id,
            'account_id' => $this->equity->id,
            'debit' => 0,
            'credit' => 100,
            'validation_status' => 'valid',
        ]);

        $approved = app(OpeningBalanceImportService::class)->approveBatch($batch, $this->user->id);

        $this->assertSame(AccountingImportBatchStatus::Completed, $approved->status);
        $journal = JournalEntry::query()->find($approved->posted_journal_entry_id);
        $this->assertNotNull($journal);
        $this->assertTrue($journal->is_opening_balance);
        $this->assertSame(JournalEntryStatus::Posted, $journal->status);
    }

    public function test_ar_aging_mismatch_blocks_approval_until_variance_approved(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '1234567890',
        ]);

        $batch = OpeningBalanceImportBatch::query()->create([
            'cutover_date' => '2026-07-01',
            'file_name' => 'ar.csv',
            'batch_type' => OpeningBalanceBatchType::ArAging,
            'imported_by' => $this->user->id,
            'status' => AccountingImportBatchStatus::Validated,
            'validation_summary' => ['balanced' => true],
        ]);

        OpeningBalanceImportLine::query()->create([
            'opening_balance_import_batch_id' => $batch->id,
            'account_id' => $this->ar->id,
            'debit' => 100,
            'credit' => 0,
            'validation_status' => 'valid',
        ]);

        OpeningBalanceImportLine::query()->create([
            'opening_balance_import_batch_id' => $batch->id,
            'account_id' => $this->equity->id,
            'debit' => 0,
            'credit' => 100,
            'validation_status' => 'valid',
        ]);

        OpeningBalanceImportLine::query()->create([
            'opening_balance_import_batch_id' => $batch->id,
            'account_id' => $this->ar->id,
            'debit' => 80,
            'credit' => 0,
            'party_type' => Customer::class,
            'party_id' => $customer->id,
            'validation_status' => 'valid',
        ]);

        $reconciliation = OpeningBalanceReconciliation::query()->create([
            'opening_balance_import_batch_id' => $batch->id,
            'reconciliation_type' => 'ar_aging',
            'source_total' => 100,
            'import_total' => 80,
            'variance' => 20,
            'status' => OpeningBalanceReconciliationStatus::Unreconciled,
        ]);

        $service = app(OpeningBalanceImportService::class);

        $this->expectException(DomainException::class);
        $service->approveBatch($batch->fresh(['lines', 'reconciliations']), $this->user->id);

        $service->approveVariance($reconciliation, $this->user->id);
        $approved = $service->approveBatch($batch->fresh(['lines', 'reconciliations']), $this->user->id, 25.0);

        $this->assertSame(AccountingImportBatchStatus::Completed, $approved->status);
    }
}
