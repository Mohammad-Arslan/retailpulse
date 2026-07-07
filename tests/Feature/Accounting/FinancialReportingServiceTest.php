<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\FiscalYearStatus;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\Accounting\FinancialReportingService;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FinancialReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_balance_nets_to_zero_after_balanced_posting(): void
    {
        $cash = ChartOfAccount::query()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset', 'is_postable' => true]);
        $revenue = ChartOfAccount::query()->create(['code' => '4100', 'name' => 'Revenue', 'type' => 'revenue', 'is_postable' => true]);
        $user = User::factory()->create(['is_active' => true]);

        FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Open,
        ]);

        $journalService = app(JournalService::class);
        $entry = $journalService->createDraft([
            'journal_date' => '2026-06-15',
            'description' => 'Reporting smoke test',
        ], [
            ['account_id' => $cash->id, 'debit' => 250, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 250],
        ], $user->id);

        $journalService->post($entry, $user->id);

        $report = app(FinancialReportingService::class)->trialBalance([
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]);

        $this->assertSame(250.0, $report['totals']['period_debit']);
        $this->assertSame(250.0, $report['totals']['period_credit']);
        $this->assertSame($report['totals']['closing_debit'], $report['totals']['closing_credit']);
    }

    public function test_general_ledger_returns_posted_lines_for_account(): void
    {
        $cash = ChartOfAccount::query()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset', 'is_postable' => true]);
        $revenue = ChartOfAccount::query()->create(['code' => '4100', 'name' => 'Revenue', 'type' => 'revenue', 'is_postable' => true]);
        $user = User::factory()->create(['is_active' => true]);

        FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Open,
        ]);

        $journalService = app(JournalService::class);
        $entry = $journalService->createDraft([
            'journal_date' => '2026-06-20',
            'description' => 'GL smoke test',
        ], [
            ['account_id' => $cash->id, 'debit' => 75, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 75],
        ], $user->id);

        $posted = $journalService->post($entry, $user->id);

        $ledger = app(FinancialReportingService::class)->generalLedger([
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'account_id' => $cash->id,
        ]);

        $this->assertNotEmpty($ledger['rows']);
        $this->assertSame($posted->id, $ledger['rows'][0]['journal_entry_id'] ?? null);
    }
}
