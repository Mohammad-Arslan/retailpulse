<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\FiscalYearStatus;
use App\Enums\JournalEntryStatus;
use App\Models\ChartOfAccount;
use App\Models\FinancialSetting;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Services\Accounting\JournalValidationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class JournalValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChartOfAccount $cash;

    private ChartOfAccount $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cash = ChartOfAccount::query()->create([
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $this->revenue = ChartOfAccount::query()->create([
            'code' => '4000',
            'name' => 'Revenue',
            'type' => 'revenue',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEntry(array $overrides = []): JournalEntry
    {
        return JournalEntry::query()->create([
            'journal_number' => 'JV-'.random_int(10000, 99999),
            'journal_date' => '2026-06-15',
            'status' => JournalEntryStatus::Draft,
            ...$overrides,
        ]);
    }

    private function addBalancedLines(JournalEntry $entry, float $amount = 100.0): void
    {
        JournalTransaction::query()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->cash->id,
            'debit' => $amount,
            'credit' => 0,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->revenue->id,
            'debit' => 0,
            'credit' => $amount,
        ]);
    }

    public function test_assert_can_post_treats_float_imprecise_lines_as_balanced(): void
    {
        $entry = $this->createEntry();

        foreach ([0.10, 0.10, 0.10] as $amount) {
            JournalTransaction::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $this->cash->id,
                'debit' => $amount,
                'credit' => 0,
            ]);
        }

        JournalTransaction::query()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->revenue->id,
            'debit' => 0,
            'credit' => 0.30,
        ]);

        app(JournalValidationService::class)->assertCanPost($entry->fresh());

        $this->addToAssertionCount(1);
    }

    public function test_assert_can_post_rejects_genuinely_unbalanced_journal(): void
    {
        $entry = $this->createEntry();

        JournalTransaction::query()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->cash->id,
            'debit' => 100,
            'credit' => 0,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->revenue->id,
            'debit' => 0,
            'credit' => 99.99,
        ]);

        $this->expectException(DomainException::class);

        app(JournalValidationService::class)->assertCanPost($entry->fresh());
    }

    public function test_assert_can_post_rejects_journal_with_no_lines(): void
    {
        $entry = $this->createEntry();

        $this->expectException(DomainException::class);

        app(JournalValidationService::class)->assertCanPost($entry->fresh());
    }

    public function test_assert_can_post_rejects_journal_in_closed_fiscal_year(): void
    {
        $fiscalYear = FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Closed,
        ]);

        $entry = $this->createEntry(['fiscal_year_id' => $fiscalYear->id]);
        $this->addBalancedLines($entry);

        $this->expectException(DomainException::class);

        app(JournalValidationService::class)->assertCanPost($entry->fresh());
    }

    public function test_assert_can_post_rejects_journal_before_cutover_date(): void
    {
        FinancialSetting::query()->create([
            'functional_currency_code' => 'USD',
            'fiscal_year_start_month' => 1,
            'accounting_cutover_date' => '2026-07-01',
        ]);

        $fiscalYear = FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Open,
        ]);

        $entry = $this->createEntry(['fiscal_year_id' => $fiscalYear->id, 'journal_date' => '2026-06-15']);
        $this->addBalancedLines($entry);

        $this->expectException(DomainException::class);

        app(JournalValidationService::class)->assertCanPost($entry->fresh());
    }

    public function test_assert_can_edit_allows_draft_journal(): void
    {
        $entry = $this->createEntry(['status' => JournalEntryStatus::Draft]);

        app(JournalValidationService::class)->assertCanEdit($entry);

        $this->addToAssertionCount(1);
    }

    public function test_assert_can_edit_rejects_posted_journal(): void
    {
        $entry = $this->createEntry(['status' => JournalEntryStatus::Posted]);

        $this->expectException(DomainException::class);

        app(JournalValidationService::class)->assertCanEdit($entry);
    }

    public function test_assert_can_reverse_allows_posted_journal_in_open_period(): void
    {
        $fiscalYear = FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Open,
        ]);

        $entry = $this->createEntry(['status' => JournalEntryStatus::Posted, 'fiscal_year_id' => $fiscalYear->id]);

        app(JournalValidationService::class)->assertCanReverse($entry);

        $this->addToAssertionCount(1);
    }

    public function test_assert_can_reverse_rejects_non_posted_journal(): void
    {
        $entry = $this->createEntry(['status' => JournalEntryStatus::Draft]);

        $this->expectException(DomainException::class);

        app(JournalValidationService::class)->assertCanReverse($entry);
    }

    public function test_assert_can_reverse_rejects_journal_in_closed_fiscal_year(): void
    {
        $fiscalYear = FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Closed,
        ]);

        $entry = $this->createEntry(['status' => JournalEntryStatus::Posted, 'fiscal_year_id' => $fiscalYear->id]);

        $this->expectException(DomainException::class);

        app(JournalValidationService::class)->assertCanReverse($entry);
    }
}
