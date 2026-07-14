<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PostingRuleEntrySide;
use App\Enums\PostingRuleWarehouseScope;
use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Events\SaleCompleted;
use App\Events\TransferConfirmed;
use App\Listeners\Accounting\ProcessAccountingOnSaleCompleted;
use App\Listeners\Accounting\ProcessAccountingOnTransferConfirmed;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\PostingRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class TransferAndSplitTenderPostingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();
    }

    public function test_transfer_confirmed_moves_inventory_between_warehouse_accounts(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Transfer Branch',
            'code' => 'TRB',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $from = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'From WH',
            'code' => 'FROM',
            'is_default' => true,
            'is_active' => true,
        ]);
        $to = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'To WH',
            'code' => 'TO',
            'is_default' => false,
            'is_active' => true,
        ]);

        $sourceInventory = ChartOfAccount::query()->create([
            'code' => '1401',
            'name' => 'Inventory From',
            'type' => 'asset',
        ]);
        $destInventory = ChartOfAccount::query()->create([
            'code' => '1402',
            'name' => 'Inventory To',
            'type' => 'asset',
        ]);

        AccountMapping::query()->where('mapping_key', 'inventory_asset')->delete();
        AccountMapping::query()->create([
            'mapping_key' => 'inventory_asset',
            'account_id' => $sourceInventory->id,
            'warehouse_id' => $from->id,
            'status' => 'active',
            'priority' => 50,
        ]);
        AccountMapping::query()->create([
            'mapping_key' => 'inventory_asset',
            'account_id' => $destInventory->id,
            'warehouse_id' => $to->id,
            'status' => 'active',
            'priority' => 50,
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Transfer Widget',
            'slug' => 'transfer-widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'TR-VAR-1',
            'sell_price' => 50,
            'is_default' => true,
        ]);

        app(\App\Services\Accounting\CostService::class)->createLayerOnReceive(
            productVariantId: $variant->id,
            warehouseId: $from->id,
            qtyReceived: 10,
            unitCost: 20,
            sourceReferenceType: 'Tests\\TransferGrn',
            sourceReferenceId: 1,
        );

        $user = User::factory()->create(['is_active' => true]);

        $transfer = StockTransfer::query()->create([
            'reference_no' => 'TR-100',
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => 'received',
            'created_by' => $user->id,
            'received_by' => $user->id,
            'received_at' => now(),
        ]);

        StockTransferItem::query()->create([
            'stock_transfer_id' => $transfer->id,
            'product_variant_id' => $variant->id,
            'qty_requested' => 5,
            'qty_received' => 5,
        ]);

        app(ProcessAccountingOnTransferConfirmed::class)->handle(
            new TransferConfirmed($transfer->fresh(['items', 'fromWarehouse', 'toWarehouse']))
        );

        $journal = JournalEntry::query()->where('source_event', 'transfer.confirmed')->with('transactions')->firstOrFail();

        $this->assertSame(100.0, (float) $journal->transactions->sum('debit'));
        $this->assertSame(100.0, (float) $journal->transactions->sum('credit'));

        $debit = $journal->transactions->firstWhere(fn (JournalTransaction $t) => (float) $t->debit > 0);
        $credit = $journal->transactions->firstWhere(fn (JournalTransaction $t) => (float) $t->credit > 0);

        $this->assertSame($destInventory->id, $debit->account_id);
        $this->assertSame($sourceInventory->id, $credit->account_id);
        $this->assertSame($to->id, $debit->warehouse_id);
        $this->assertSame($from->id, $credit->warehouse_id);
    }

    public function test_split_tender_sale_debits_each_payment_method_account(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Split Branch',
            'code' => 'SPB',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $warehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Split Widget',
            'slug' => 'split-widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SPLIT-1',
            'sell_price' => 100,
            'is_default' => true,
        ]);

        app(\App\Services\Accounting\FinancialSettingsService::class)->get()
            ->update(['allow_negative_inventory' => true]);

        $cashier = User::factory()->create(['is_active' => true]);

        $sale = Sale::query()->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cashier_id' => $cashier->id,
            'status' => SaleStatus::Completed,
            'subtotal' => 100,
            'total_discount' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'balance_due' => 0,
            'currency' => 'USD',
            'completed_at' => now(),
        ]);

        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name' => 'Split Widget',
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total_inc_tax' => 100,
        ]);

        SalePayment::query()->create([
            'sale_id' => $sale->id,
            'cashier_id' => $cashier->id,
            'method' => PaymentMethod::Cash,
            'amount' => 40,
            'status' => PaymentStatus::Completed,
            'created_at' => now(),
        ]);
        SalePayment::query()->create([
            'sale_id' => $sale->id,
            'cashier_id' => $cashier->id,
            'method' => PaymentMethod::Card,
            'amount' => 60,
            'status' => PaymentStatus::Completed,
            'created_at' => now(),
        ]);

        app(ProcessAccountingOnSaleCompleted::class)->handle(
            new SaleCompleted($sale->fresh(['items', 'payments', 'invoice']))
        );

        $journal = JournalEntry::query()->where('source_event', 'sale.completed')->with('transactions')->firstOrFail();

        $cashAccountId = (int) AccountMapping::query()
            ->where('mapping_key', 'payment_method_account')
            ->where('payment_method', 'cash')
            ->value('account_id');
        $cardAccountId = (int) AccountMapping::query()
            ->where('mapping_key', 'payment_method_account')
            ->where('payment_method', 'card')
            ->value('account_id');

        $cashDebit = (float) $journal->transactions->where('account_id', $cashAccountId)->sum('debit');
        $cardDebit = (float) $journal->transactions->where('account_id', $cardAccountId)->sum('debit');

        $this->assertSame(40.0, $cashDebit);
        $this->assertSame(60.0, $cardDebit);
        $this->assertSame(
            (float) $journal->transactions->sum('debit'),
            (float) $journal->transactions->sum('credit'),
        );
    }

    public function test_warehouse_scope_is_honoured_by_posting_rule_engine(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Scope Branch',
            'code' => 'SCP',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $fromWh = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Scope From',
            'code' => 'SFROM',
            'is_default' => true,
            'is_active' => true,
        ]);
        $toWh = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Scope To',
            'code' => 'STO',
            'is_default' => false,
            'is_active' => true,
        ]);

        $fromAccount = ChartOfAccount::query()->create(['code' => '1491', 'name' => 'WH From', 'type' => 'asset']);
        $toAccount = ChartOfAccount::query()->create(['code' => '1492', 'name' => 'WH To', 'type' => 'asset']);

        AccountMapping::query()->create([
            'mapping_key' => 'inventory_asset',
            'account_id' => $fromAccount->id,
            'warehouse_id' => $fromWh->id,
            'status' => 'active',
        ]);
        AccountMapping::query()->create([
            'mapping_key' => 'inventory_asset',
            'account_id' => $toAccount->id,
            'warehouse_id' => $toWh->id,
            'status' => 'active',
        ]);

        $ruleSet = \App\Models\PostingRuleSet::query()->create([
            'code' => 'TEST-TRANSFER-SCOPE',
            'name' => 'Transfer Scope',
            'event_type' => 'test.transfer.scope',
            'effective_from' => '2020-01-01',
        ]);

        $ruleSet->lines()->create([
            'sequence' => 1,
            'entry_side' => PostingRuleEntrySide::Debit,
            'account_resolution_type' => AccountResolutionType::AccountMapping,
            'account_mapping_key' => 'inventory_asset',
            'warehouse_scope' => PostingRuleWarehouseScope::Destination,
            'amount_source' => AmountSource::InventoryCost,
            'required' => true,
        ]);
        $ruleSet->lines()->create([
            'sequence' => 2,
            'entry_side' => PostingRuleEntrySide::Credit,
            'account_resolution_type' => AccountResolutionType::AccountMapping,
            'account_mapping_key' => 'inventory_asset',
            'warehouse_scope' => PostingRuleWarehouseScope::Source,
            'amount_source' => AmountSource::InventoryCost,
            'required' => true,
        ]);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('test.transfer.scope', [
            'inventory_cost' => 50,
            'from_warehouse_id' => $fromWh->id,
            'to_warehouse_id' => $toWh->id,
            'date' => '2026-06-01',
        ]);

        $this->assertSame($toAccount->id, $lines[0]['account_id']);
        $this->assertSame($fromAccount->id, $lines[1]['account_id']);
    }
}
