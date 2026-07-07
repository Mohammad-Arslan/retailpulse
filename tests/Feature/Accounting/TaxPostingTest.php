<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\JournalEntryStatus;
use App\Enums\PostingRuleEntrySide;
use App\Enums\TaxCalculationMethod;
use App\Enums\TaxDirection;
use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\PostingRuleSet;
use App\Models\TaxType;
use App\Models\User;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\TaxLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TaxPostingTest extends TestCase
{
    use RefreshDatabase;

    private TaxType $gstExclusive;

    private TaxType $whtPurchase;

    protected function setUp(): void
    {
        parent::setUp();

        $outputTax = ChartOfAccount::query()->create(['code' => '2200', 'name' => 'Output Tax', 'type' => 'liability']);
        $inputTax = ChartOfAccount::query()->create(['code' => '1350', 'name' => 'Input Tax', 'type' => 'asset']);
        $cash = ChartOfAccount::query()->create(['code' => '1100', 'name' => 'Cash', 'type' => 'asset']);
        $revenue = ChartOfAccount::query()->create(['code' => '4100', 'name' => 'Revenue', 'type' => 'revenue']);

        AccountMapping::query()->create(['mapping_key' => 'output_tax', 'account_id' => $outputTax->id, 'status' => 'active', 'priority' => 100]);
        AccountMapping::query()->create(['mapping_key' => 'input_tax', 'account_id' => $inputTax->id, 'status' => 'active', 'priority' => 100]);
        AccountMapping::query()->create(['mapping_key' => 'sales_revenue', 'account_id' => $revenue->id, 'status' => 'active', 'priority' => 100]);
        AccountMapping::query()->create(['mapping_key' => 'payment_method_account', 'account_id' => $cash->id, 'payment_method' => 'cash', 'status' => 'active', 'priority' => 100]);

        $this->gstExclusive = TaxType::query()->create([
            'name' => 'GST 5%',
            'code' => 'GST5',
            'rate' => 5.00,
            'tax_direction' => TaxDirection::Both,
            'calculation_method' => TaxCalculationMethod::Exclusive,
            'output_tax_account_id' => $outputTax->id,
            'input_tax_account_id' => $inputTax->id,
            'recoverable_percentage' => 100,
            'effective_from' => '2020-01-01',
            'status' => 'active',
        ]);

        $this->whtPurchase = TaxType::query()->create([
            'name' => 'WHT 10%',
            'code' => 'WHT10',
            'rate' => 10.00,
            'tax_direction' => TaxDirection::Purchase,
            'calculation_method' => TaxCalculationMethod::Exclusive,
            'input_tax_account_id' => $inputTax->id,
            'recoverable_percentage' => 50,
            'effective_from' => '2020-01-01',
            'status' => 'active',
        ]);

        $ruleSet = PostingRuleSet::query()->create([
            'code' => 'TAX-SALE',
            'name' => 'Tax Sale',
            'event_type' => 'sale.completed',
            'effective_from' => '2020-01-01',
        ]);

        $ruleSet->lines()->create([
            'sequence' => 1,
            'entry_side' => PostingRuleEntrySide::Debit,
            'account_resolution_type' => AccountResolutionType::PaymentMethodAccount,
            'amount_source' => AmountSource::SettlementAmount,
            'required' => true,
        ]);

        $ruleSet->lines()->create([
            'sequence' => 2,
            'entry_side' => PostingRuleEntrySide::Credit,
            'account_resolution_type' => AccountResolutionType::AccountMapping,
            'account_mapping_key' => 'sales_revenue',
            'amount_source' => AmountSource::NetAmount,
            'required' => true,
        ]);

        $ruleSet->lines()->create([
            'sequence' => 3,
            'entry_side' => PostingRuleEntrySide::Credit,
            'account_resolution_type' => AccountResolutionType::TaxAccount,
            'account_mapping_key' => 'output_tax',
            'amount_source' => AmountSource::TaxAmount,
            'required' => false,
        ]);
    }

    public function test_calculate_tax_exclusive(): void
    {
        $result = app(TaxLedgerService::class)->calculateTax(100.0, $this->gstExclusive, 'sales');

        $this->assertSame(105.0, $result['gross_amount']);
        $this->assertSame(100.0, $result['net_amount']);
        $this->assertSame(5.0, $result['tax_amount']);
    }

    public function test_calculate_tax_inclusive(): void
    {
        $inclusive = TaxType::query()->create([
            'name' => 'GST 5% Inclusive',
            'code' => 'GST5INC',
            'rate' => 5.00,
            'tax_direction' => TaxDirection::Both,
            'calculation_method' => TaxCalculationMethod::Inclusive,
            'output_tax_account_id' => ChartOfAccount::query()->where('code', '2200')->value('id'),
            'input_tax_account_id' => ChartOfAccount::query()->where('code', '1350')->value('id'),
            'recoverable_percentage' => 100,
            'effective_from' => '2020-01-01',
            'status' => 'active',
        ]);

        $result = app(TaxLedgerService::class)->calculateTax(105.0, $inclusive, 'sales');

        $this->assertSame(105.0, $result['gross_amount']);
        $this->assertSame(100.0, $result['net_amount']);
        $this->assertSame(5.0, $result['tax_amount']);
    }

    public function test_sale_with_tax_stamps_tax_type_on_journal_line(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $event = app(AccountingEventService::class)->process(
            'sale.completed',
            'App\\Models\\Sale',
            9001,
            [
                'date' => '2026-06-15',
                'gross_amount' => 105.0,
                'net_amount' => 100.0,
                'tax_amount' => 5.0,
                'settlement_amount' => 105.0,
                'payment_method' => 'cash',
                'tax_type_id' => $this->gstExclusive->id,
                'tax_direction' => 'sales',
                'user_id' => $user->id,
            ],
            $user->id,
        );

        $journal = JournalEntry::query()->with('transactions')->findOrFail($event->journal_entry_id);
        $taxLine = $journal->transactions->firstWhere('tax_type_id', $this->gstExclusive->id);

        $this->assertNotNull($taxLine);
        $this->assertSame(5.0, (float) $taxLine->credit);
    }

    public function test_purchase_tax_applies_recoverable_percentage(): void
    {
        $result = app(TaxLedgerService::class)->calculateTax(100.0, $this->whtPurchase, 'purchase');

        $this->assertSame(10.0, $result['tax_amount']);
        $this->assertSame(5.0, $result['taxable_amount']);
    }

    public function test_tax_ledger_summarize_totals_posted_tax_lines(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $journal = JournalEntry::query()->create([
            'journal_number' => 'JV-TAX-1',
            'journal_date' => '2026-06-15',
            'status' => JournalEntryStatus::Posted,
            'posted_at' => now(),
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journal->id,
            'account_id' => ChartOfAccount::query()->where('code', '2200')->value('id'),
            'credit' => 15.0,
            'tax_type_id' => $this->gstExclusive->id,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journal->id,
            'account_id' => ChartOfAccount::query()->where('code', '1350')->value('id'),
            'debit' => 4.0,
            'tax_type_id' => $this->gstExclusive->id,
        ]);

        $summary = app(TaxLedgerService::class)->summarize('2026-06-01', '2026-06-30')->first();

        $this->assertNotNull($summary);
        $this->assertSame(15.0, $summary['output_tax']);
        $this->assertSame(4.0, $summary['input_tax']);
        $this->assertSame(11.0, $summary['net_payable']);
    }
}
