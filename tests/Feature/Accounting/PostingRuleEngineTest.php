<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use App\Models\AccountMapping;
use App\Models\AssetCategory;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\PostingRuleSet;
use App\Services\Accounting\PostingRuleEngine;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PostingRuleEngineTest extends TestCase
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

    private function createRuleSet(string $eventType): PostingRuleSet
    {
        return PostingRuleSet::query()->create([
            'code' => 'TEST-'.strtoupper(str_replace('.', '-', $eventType)),
            'name' => $eventType,
            'event_type' => $eventType,
            'effective_from' => '2020-01-01',
        ]);
    }

    private function addLine(
        PostingRuleSet $ruleSet,
        int $sequence,
        PostingRuleEntrySide $entrySide,
        AccountResolutionType $resolutionType,
        AmountSource $amountSource,
        ?int $accountId = null,
        ?string $mappingKey = null,
        bool $required = true,
    ): void {
        $ruleSet->lines()->create([
            'sequence' => $sequence,
            'entry_side' => $entrySide,
            'account_resolution_type' => $resolutionType,
            'account_id' => $accountId,
            'account_mapping_key' => $mappingKey,
            'amount_source' => $amountSource,
            'required' => $required,
        ]);
    }

    public function test_required_line_resolving_to_zero_amount_throws(): void
    {
        $ruleSet = $this->createRuleSet('test.required_zero');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::FixedAccount, AmountSource::TaxAmount, $this->cash->id);

        $this->expectException(DomainException::class);

        app(PostingRuleEngine::class)->buildJournalLines('test.required_zero', ['date' => '2026-06-15']);
    }

    public function test_optional_line_resolving_to_zero_amount_is_silently_skipped(): void
    {
        $ruleSet = $this->createRuleSet('test.optional_zero');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::FixedAccount, AmountSource::TaxAmount, $this->cash->id, required: false);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.optional_zero', [
            'date' => '2026-06-15',
            'gross_amount' => 100,
        ]);

        $this->assertCount(1, $lines);
        $this->assertSame($this->revenue->id, $lines[0]['account_id']);
    }

    public function test_fixed_account_resolution_uses_the_lines_own_account(): void
    {
        $ruleSet = $this->createRuleSet('test.fixed_account');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->cash->id);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.fixed_account', ['date' => '2026-06-15', 'gross_amount' => 50]);

        $this->assertSame($this->cash->id, $lines[0]['account_id']);
        $this->assertSame($this->revenue->id, $lines[1]['account_id']);
        $this->assertSame(50.0, $lines[0]['debit']);
        $this->assertSame(50.0, $lines[1]['credit']);
    }

    public function test_account_mapping_resolution_resolves_by_mapping_key(): void
    {
        AccountMapping::query()->create([
            'mapping_key' => 'custom_mapping',
            'account_id' => $this->cash->id,
            'status' => 'active',
        ]);

        $ruleSet = $this->createRuleSet('test.account_mapping');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::GrossAmount, mappingKey: 'custom_mapping');
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.account_mapping', ['date' => '2026-06-15', 'gross_amount' => 25]);

        $this->assertSame($this->cash->id, $lines[0]['account_id']);
    }

    public function test_configurable_mapping_resolution_resolves_by_mapping_key_like_account_mapping(): void
    {
        AccountMapping::query()->create([
            'mapping_key' => 'configurable_key',
            'account_id' => $this->cash->id,
            'status' => 'active',
        ]);

        $ruleSet = $this->createRuleSet('test.configurable_mapping');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::ConfigurableMapping, AmountSource::GrossAmount, mappingKey: 'configurable_key');
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.configurable_mapping', ['date' => '2026-06-15', 'gross_amount' => 25]);

        $this->assertSame($this->cash->id, $lines[0]['account_id']);
    }

    public function test_customer_receivable_account_resolution_uses_accounts_receivable_mapping(): void
    {
        $ar = ChartOfAccount::query()->create(['code' => '1100', 'name' => 'AR', 'type' => 'asset']);
        AccountMapping::query()->create(['mapping_key' => 'accounts_receivable', 'account_id' => $ar->id, 'status' => 'active']);

        $ruleSet = $this->createRuleSet('test.customer_receivable');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::CustomerReceivableAccount, AmountSource::GrossAmount);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.customer_receivable', ['date' => '2026-06-15', 'gross_amount' => 40]);

        $this->assertSame($ar->id, $lines[0]['account_id']);
    }

    public function test_supplier_payable_account_resolution_uses_accounts_payable_mapping(): void
    {
        $ap = ChartOfAccount::query()->create(['code' => '2100', 'name' => 'AP', 'type' => 'liability']);
        AccountMapping::query()->create(['mapping_key' => 'accounts_payable', 'account_id' => $ap->id, 'status' => 'active']);

        $ruleSet = $this->createRuleSet('test.supplier_payable');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->cash->id);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::SupplierPayableAccount, AmountSource::GrossAmount);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.supplier_payable', ['date' => '2026-06-15', 'gross_amount' => 40]);

        $this->assertSame($ap->id, $lines[1]['account_id']);
    }

    public function test_payment_method_account_resolution_falls_back_to_cash_on_hand(): void
    {
        AccountMapping::query()->create(['mapping_key' => 'cash_on_hand', 'account_id' => $this->cash->id, 'status' => 'active']);

        $ruleSet = $this->createRuleSet('test.payment_method');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::PaymentMethodAccount, AmountSource::GrossAmount);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.payment_method', ['date' => '2026-06-15', 'gross_amount' => 40]);

        $this->assertSame($this->cash->id, $lines[0]['account_id']);
    }

    public function test_warehouse_inventory_account_resolution_uses_inventory_asset_mapping(): void
    {
        $inventory = ChartOfAccount::query()->create(['code' => '1200', 'name' => 'Inventory', 'type' => 'asset']);
        AccountMapping::query()->create(['mapping_key' => 'inventory_asset', 'account_id' => $inventory->id, 'status' => 'active']);

        $ruleSet = $this->createRuleSet('test.warehouse_inventory');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::WarehouseInventoryAccount, AmountSource::InventoryCost);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::InventoryCost, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.warehouse_inventory', ['date' => '2026-06-15', 'inventory_cost' => 40]);

        $this->assertSame($inventory->id, $lines[0]['account_id']);
    }

    public function test_tax_account_resolution_uses_output_tax_for_sales_by_default(): void
    {
        $outputTax = ChartOfAccount::query()->create(['code' => '2200', 'name' => 'Output Tax', 'type' => 'liability']);
        AccountMapping::query()->create(['mapping_key' => 'output_tax', 'account_id' => $outputTax->id, 'status' => 'active']);

        $ruleSet = $this->createRuleSet('test.tax_sales');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::FixedAccount, AmountSource::TaxAmount, $this->cash->id);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::TaxAccount, AmountSource::TaxAmount);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.tax_sales', ['date' => '2026-06-15', 'tax_amount' => 10]);

        $this->assertSame($outputTax->id, $lines[1]['account_id']);
    }

    public function test_tax_account_resolution_uses_input_tax_for_purchases(): void
    {
        $inputTax = ChartOfAccount::query()->create(['code' => '1300', 'name' => 'Input Tax', 'type' => 'asset']);
        AccountMapping::query()->create(['mapping_key' => 'input_tax', 'account_id' => $inputTax->id, 'status' => 'active']);

        $ruleSet = $this->createRuleSet('test.tax_purchase');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::TaxAccount, AmountSource::TaxAmount);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::TaxAmount, $this->cash->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.tax_purchase', [
            'date' => '2026-06-15',
            'tax_amount' => 10,
            'tax_direction' => 'purchase',
        ]);

        $this->assertSame($inputTax->id, $lines[0]['account_id']);
    }

    /**
     * BankAccount and ProductCategoryAccount are pre-existing gaps: resolveAccount()'s
     * match falls through to `default => $line->account` for both, so they behave like
     * FixedAccount rather than performing any bank- or category-specific lookup. This is
     * documented, not fixed, per this phase's scope — the test pins today's behavior.
     */
    public function test_bank_account_and_product_category_account_fall_through_to_the_lines_own_account(): void
    {
        $ruleSet = $this->createRuleSet('test.bank_account_fallthrough');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::BankAccount, AmountSource::GrossAmount, $this->cash->id);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::ProductCategoryAccount, AmountSource::GrossAmount, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.bank_account_fallthrough', ['date' => '2026-06-15', 'gross_amount' => 15]);

        $this->assertSame($this->cash->id, $lines[0]['account_id']);
        $this->assertSame($this->revenue->id, $lines[1]['account_id']);
    }

    private function createFixedAsset(AssetCategory $category, array $overrides = []): FixedAsset
    {
        return FixedAsset::query()->create([
            'asset_code' => 'FA-'.random_int(10000, 99999),
            'name' => 'Delivery Van',
            'category_id' => $category->id,
            'acquisition_cost' => 12000,
            'acquisition_date' => '2026-01-01',
            'useful_life_months' => 60,
            ...$overrides,
        ]);
    }

    public function test_asset_account_resolution_uses_the_assets_own_account_columns(): void
    {
        $category = AssetCategory::query()->create(['name' => 'Vehicles', 'code' => 'VEH']);
        $assetAccount = ChartOfAccount::query()->create(['code' => '1500', 'name' => 'Fixed Assets', 'type' => 'asset']);
        $accumDepAccount = ChartOfAccount::query()->create(['code' => '1510', 'name' => 'Accumulated Depreciation', 'type' => 'asset']);
        $depExpenseAccount = ChartOfAccount::query()->create(['code' => '5500', 'name' => 'Depreciation Expense', 'type' => 'expense']);

        $asset = $this->createFixedAsset($category, [
            'asset_account_id' => $assetAccount->id,
            'accumulated_depreciation_account_id' => $accumDepAccount->id,
            'depreciation_expense_account_id' => $depExpenseAccount->id,
        ]);

        $ruleSet = $this->createRuleSet('test.asset_depreciation');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AssetAccount, AmountSource::DepreciationAmount, mappingKey: 'depreciation_expense_account');
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::AssetAccount, AmountSource::DepreciationAmount, mappingKey: 'accumulated_depreciation_account');

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.asset_depreciation', [
            'date' => '2026-06-15',
            'fixed_asset_id' => $asset->id,
            'depreciation_amount' => 100,
        ]);

        $this->assertSame($depExpenseAccount->id, $lines[0]['account_id']);
        $this->assertSame($accumDepAccount->id, $lines[1]['account_id']);
    }

    public function test_asset_account_resolution_falls_back_to_category_account_when_asset_column_is_null(): void
    {
        $categoryAssetAccount = ChartOfAccount::query()->create(['code' => '1501', 'name' => 'Category Fixed Assets', 'type' => 'asset']);
        $category = AssetCategory::query()->create([
            'name' => 'Vehicles',
            'code' => 'VEH2',
            'asset_account_id' => $categoryAssetAccount->id,
        ]);

        $asset = $this->createFixedAsset($category);

        $ruleSet = $this->createRuleSet('test.asset_fallback');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AssetAccount, AmountSource::GrossAmount, mappingKey: 'asset_account');
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->cash->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.asset_fallback', [
            'date' => '2026-06-15',
            'fixed_asset_id' => $asset->id,
            'gross_amount' => 200,
        ]);

        $this->assertSame($categoryAssetAccount->id, $lines[0]['account_id']);
    }

    public function test_asset_account_resolution_throws_for_required_line_when_fixed_asset_id_missing(): void
    {
        $ruleSet = $this->createRuleSet('test.asset_missing_id');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AssetAccount, AmountSource::GrossAmount, mappingKey: 'asset_account');
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->cash->id);

        $this->expectException(DomainException::class);

        app(PostingRuleEngine::class)->buildJournalLines('test.asset_missing_id', ['date' => '2026-06-15', 'gross_amount' => 50]);
    }

    public function test_asset_account_resolution_throws_for_invalid_mapping_key_role(): void
    {
        $category = AssetCategory::query()->create(['name' => 'Vehicles', 'code' => 'VEH3']);
        $asset = $this->createFixedAsset($category);

        $ruleSet = $this->createRuleSet('test.asset_invalid_role');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AssetAccount, AmountSource::GrossAmount, mappingKey: 'not_a_real_role');
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->cash->id);

        $this->expectException(DomainException::class);

        app(PostingRuleEngine::class)->buildJournalLines('test.asset_invalid_role', [
            'date' => '2026-06-15',
            'fixed_asset_id' => $asset->id,
            'gross_amount' => 50,
        ]);
    }

    public function test_unresolvable_account_mapping_throws_for_required_line(): void
    {
        $ruleSet = $this->createRuleSet('test.unresolvable_mapping');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::AccountMapping, AmountSource::GrossAmount, mappingKey: 'nonexistent_mapping');
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::GrossAmount, $this->revenue->id);

        $this->expectException(DomainException::class);

        app(PostingRuleEngine::class)->buildJournalLines('test.unresolvable_mapping', ['date' => '2026-06-15', 'gross_amount' => 15]);
    }

    /**
     * @return array<string, array{0: AmountSource, 1: string, 2: float}>
     */
    public static function amountSourceProvider(): array
    {
        return [
            'gross_amount' => [AmountSource::GrossAmount, 'gross_amount', 100.0],
            'net_amount' => [AmountSource::NetAmount, 'net_amount', 90.0],
            'tax_amount' => [AmountSource::TaxAmount, 'tax_amount', 10.0],
            'discount_amount' => [AmountSource::DiscountAmount, 'discount_amount', 5.0],
            'shipping_amount' => [AmountSource::ShippingAmount, 'shipping_amount', 15.0],
            'inventory_cost' => [AmountSource::InventoryCost, 'inventory_cost', 60.0],
            'landed_cost' => [AmountSource::LandedCost, 'landed_cost', 70.0],
            'exchange_difference' => [AmountSource::ExchangeDifference, 'exchange_difference', 2.5],
            'depreciation_amount' => [AmountSource::DepreciationAmount, 'depreciation_amount', 20.0],
            'custom_formula' => [AmountSource::CustomFormula, 'custom_amount', 33.0],
        ];
    }

    #[DataProvider('amountSourceProvider')]
    public function test_resolve_amount_reads_the_matching_payload_key(AmountSource $source, string $payloadKey, float $amount): void
    {
        $eventType = 'test.amount_source.'.$payloadKey;
        $ruleSet = $this->createRuleSet($eventType);
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::FixedAccount, $source, $this->cash->id);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, $source, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines($eventType, [
            'date' => '2026-06-15',
            $payloadKey => $amount,
        ]);

        $this->assertSame($amount, $lines[0]['debit']);
        $this->assertSame($amount, $lines[1]['credit']);
    }

    public function test_settlement_amount_falls_back_to_generic_amount_key(): void
    {
        $ruleSet = $this->createRuleSet('test.settlement_amount');
        $this->addLine($ruleSet, 1, PostingRuleEntrySide::Debit, AccountResolutionType::FixedAccount, AmountSource::SettlementAmount, $this->cash->id);
        $this->addLine($ruleSet, 2, PostingRuleEntrySide::Credit, AccountResolutionType::FixedAccount, AmountSource::SettlementAmount, $this->revenue->id);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.settlement_amount', [
            'date' => '2026-06-15',
            'amount' => 77.0,
        ]);

        $this->assertSame(77.0, $lines[0]['debit']);
    }
}
