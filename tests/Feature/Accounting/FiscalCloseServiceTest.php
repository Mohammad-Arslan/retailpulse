<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\FiscalYearStatus;
use App\Enums\JournalEntryStatus;
use App\Models\ChartOfAccount;
use App\Models\FinancialSetting;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\FiscalCloseService;
use App\Services\Accounting\JournalService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FiscalCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChartOfAccount $cash;

    private ChartOfAccount $revenue;

    private ChartOfAccount $retainedEarnings;

    private ChartOfAccount $currentYearEarnings;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cash = ChartOfAccount::query()->create(['code' => '1000', 'name' => 'Cash', 'type' => 'asset']);
        $this->revenue = ChartOfAccount::query()->create(['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue']);
        $this->retainedEarnings = ChartOfAccount::query()->create(['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity']);
        $this->currentYearEarnings = ChartOfAccount::query()->create(['code' => '3200', 'name' => 'Current Year Earnings', 'type' => 'equity']);
        $this->user = User::factory()->create(['is_active' => true]);
    }

    private function configureFinancialSettings(): void
    {
        FinancialSetting::query()->create([
            'functional_currency_code' => 'USD',
            'fiscal_year_start_month' => 1,
            'retained_earnings_account_id' => $this->retainedEarnings->id,
            'current_year_earnings_account_id' => $this->currentYearEarnings->id,
        ]);
    }

    private function createOpenFiscalYear(): FiscalYear
    {
        return FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Open,
        ]);
    }

    private function postSaleJournal(FiscalYear $fiscalYear, float $amount = 100.0): JournalEntry
    {
        $service = app(JournalService::class);

        $entry = $service->createDraft(
            [
                'journal_date' => '2026-06-15',
                'fiscal_year_id' => $fiscalYear->id,
                'description' => 'Sale',
            ],
            [
                ['account_id' => $this->cash->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => $amount],
            ],
            $this->user->id,
        );

        return $service->post($entry, $this->user->id);
    }

    public function test_close_locks_journals_and_posts_retained_earnings_entry(): void
    {
        $this->configureFinancialSettings();
        $fiscalYear = $this->createOpenFiscalYear();
        $entry = $this->postSaleJournal($fiscalYear, 100);

        app(FiscalCloseService::class)->close($fiscalYear, $this->user->id);

        $fiscalYear->refresh();
        $this->assertSame(FiscalYearStatus::Closed, $fiscalYear->status);
        $this->assertNotNull($fiscalYear->closed_at);
        $this->assertSame($this->user->id, $fiscalYear->closed_by);

        $this->assertNotNull($entry->fresh()->locked_at);

        $closingEntry = JournalEntry::query()->where('is_closing_entry', true)->first();
        $this->assertNotNull($closingEntry);
        $this->assertSame(JournalEntryStatus::Posted, $closingEntry->status);

        $currentYearLine = $closingEntry->transactions->firstWhere('account_id', $this->currentYearEarnings->id);
        $retainedLine = $closingEntry->transactions->firstWhere('account_id', $this->retainedEarnings->id);

        $this->assertSame(100.0, (float) $currentYearLine->debit);
        $this->assertSame(100.0, (float) $retainedLine->credit);
    }

    public function test_close_throws_when_unposted_journals_remain(): void
    {
        $this->configureFinancialSettings();
        $fiscalYear = $this->createOpenFiscalYear();

        app(JournalService::class)->createDraft(
            ['journal_date' => '2026-06-15', 'fiscal_year_id' => $fiscalYear->id],
            [
                ['account_id' => $this->cash->id, 'debit' => 50, 'credit' => 0],
                ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 50],
            ],
            $this->user->id,
        );

        $this->expectException(DomainException::class);

        app(FiscalCloseService::class)->close($fiscalYear, $this->user->id);
    }

    public function test_close_throws_when_retained_earnings_account_not_configured(): void
    {
        $fiscalYear = $this->createOpenFiscalYear();

        $this->expectException(DomainException::class);

        app(FiscalCloseService::class)->close($fiscalYear, $this->user->id);
    }

    public function test_close_rejects_further_posting_into_the_closed_period(): void
    {
        $this->configureFinancialSettings();
        $fiscalYear = $this->createOpenFiscalYear();
        $this->postSaleJournal($fiscalYear, 100);

        app(FiscalCloseService::class)->close($fiscalYear, $this->user->id);

        $journalService = app(JournalService::class);
        $newEntry = $journalService->createDraft(
            ['journal_date' => '2026-08-01', 'fiscal_year_id' => $fiscalYear->id],
            [
                ['account_id' => $this->cash->id, 'debit' => 10, 'credit' => 0],
                ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 10],
            ],
            $this->user->id,
        );

        $this->expectException(DomainException::class);

        $journalService->post($newEntry, $this->user->id);
    }

    public function test_reopen_requires_dual_approval_from_two_distinct_users(): void
    {
        $this->configureFinancialSettings();
        $fiscalYear = $this->createOpenFiscalYear();
        $entry = $this->postSaleJournal($fiscalYear, 100);

        $service = app(FiscalCloseService::class);
        $service->close($fiscalYear, $this->user->id);

        $requester = User::factory()->create(['is_active' => true]);
        $firstApprover = User::factory()->create(['is_active' => true]);
        $secondApprover = User::factory()->create(['is_active' => true]);

        $request = $service->requestReopen($fiscalYear->fresh(), $requester->id, 'Correcting a mispost');

        $request = $service->approveReopen($request, $firstApprover->id);
        $this->assertSame($firstApprover->id, $request->first_approved_by);
        $this->assertSame('pending', $request->status);
        $this->assertSame(FiscalYearStatus::Closed, $fiscalYear->fresh()->status);

        $request = $service->approveReopen($request, $secondApprover->id);
        $this->assertSame($secondApprover->id, $request->second_approved_by);
        $this->assertSame('approved', $request->status);

        $fiscalYear->refresh();
        $this->assertSame(FiscalYearStatus::Open, $fiscalYear->status);
        $this->assertNull($fiscalYear->closed_at);
        $this->assertNull($entry->fresh()->locked_at);
    }

    public function test_reopen_rejects_second_approval_from_the_same_user_as_the_first(): void
    {
        $this->configureFinancialSettings();
        $fiscalYear = $this->createOpenFiscalYear();
        $this->postSaleJournal($fiscalYear, 100);

        $service = app(FiscalCloseService::class);
        $service->close($fiscalYear, $this->user->id);

        $requester = User::factory()->create(['is_active' => true]);
        $approver = User::factory()->create(['is_active' => true]);

        $request = $service->requestReopen($fiscalYear->fresh(), $requester->id, 'Correcting a mispost');
        $request = $service->approveReopen($request, $approver->id);

        $this->expectException(DomainException::class);

        $service->approveReopen($request, $approver->id);
    }

    public function test_reopen_rejects_approval_by_the_requester(): void
    {
        $this->configureFinancialSettings();
        $fiscalYear = $this->createOpenFiscalYear();
        $this->postSaleJournal($fiscalYear, 100);

        $service = app(FiscalCloseService::class);
        $service->close($fiscalYear, $this->user->id);

        $requester = User::factory()->create(['is_active' => true]);
        $request = $service->requestReopen($fiscalYear->fresh(), $requester->id, 'Correcting a mispost');

        $this->expectException(DomainException::class);

        $service->approveReopen($request, $requester->id);
    }
}
