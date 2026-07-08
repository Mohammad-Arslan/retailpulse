<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Events\SaleCompleted;
use App\Listeners\Accounting\ProcessAccountingOnSaleCompleted;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\InventoryCostLayer;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\CostService;
use App\Services\Accounting\FinancialSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class CostServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    private Branch $branch;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();

        $this->branch = Branch::query()->create([
            'name' => 'COGS Branch',
            'code' => 'CGB',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Widget',
            'slug' => 'widget-cogs',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'WDG-COGS',
            'sell_price' => 200,
            'is_default' => true,
        ]);

        $this->cashier = User::factory()->create(['is_active' => true]);
    }

    public function test_consume_on_sale_reduces_cost_layer_and_posts_cogs_journal(): void
    {
        $layer = app(CostService::class)->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 100,
            sourceReferenceType: 'Tests\\GrnItem',
            sourceReferenceId: 1,
        );

        $sale = $this->createSaleWithItem(quantity: 5, unitPrice: 200);

        app(ProcessAccountingOnSaleCompleted::class)->handle(new SaleCompleted($sale->fresh(['items', 'payments', 'invoice'])));

        $layer->refresh();
        $this->assertSame(5.0, (float) $layer->qty_remaining);

        $saleItem = $sale->items()->first();
        $this->assertSame(500.0, (float) $saleItem->cost_consumed);
        $this->assertNotNull($saleItem->cogs_journal_entry_id);

        $cogsAccount = ChartOfAccount::query()->where('code', '5100')->firstOrFail();
        $inventoryAccount = ChartOfAccount::query()->where('code', '1400')->firstOrFail();

        $journal = JournalEntry::query()
            ->with('transactions')
            ->findOrFail($saleItem->cogs_journal_entry_id);

        $cogsDebit = (float) $journal->transactions
            ->where('account_id', $cogsAccount->id)
            ->sum('debit');
        $inventoryCredit = (float) $journal->transactions
            ->where('account_id', $inventoryAccount->id)
            ->sum('credit');

        $this->assertSame(500.0, $cogsDebit);
        $this->assertSame(500.0, $inventoryCredit);
    }

    public function test_restore_on_return_creates_cost_layer_at_original_unit_cost(): void
    {
        $costService = app(CostService::class);

        $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 100,
            sourceReferenceType: 'Tests\\GrnItem',
            sourceReferenceId: 2,
        );

        $sale = $this->createSaleWithItem(quantity: 3, unitPrice: 200);
        $item = $sale->items()->first();
        $item->update(['cost_consumed' => 300]);

        $costService->consumeOnSale($item->fresh());

        $restored = $costService->restoreOnReturn($item->fresh(), 2, 100);

        $this->assertSame(2.0, (float) $restored->qty_remaining);
        $this->assertSame(100.0, (float) $restored->unit_cost);
        $this->assertSame(SaleItem::class, $restored->source_reference_type);
    }

    public function test_consume_on_sale_without_layers_uses_zero_when_negative_inventory_allowed(): void
    {
        $settings = app(FinancialSettingsService::class)->get();
        $settings->update(['allow_negative_inventory' => true]);

        $sale = $this->createSaleWithItem(quantity: 2, unitPrice: 50);
        $item = $sale->items()->first();

        $cost = app(CostService::class)->consumeOnSale($item);

        $this->assertSame(0.0, $cost);
        $this->assertSame(0, InventoryCostLayer::query()->count());
    }

    private function createSaleWithItem(int $quantity, float $unitPrice): Sale
    {
        $lineTotal = $quantity * $unitPrice;

        $sale = Sale::query()->create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'cashier_id' => $this->cashier->id,
            'status' => SaleStatus::Completed,
            'subtotal' => $lineTotal,
            'total_discount' => 0,
            'tax_total' => 0,
            'grand_total' => $lineTotal,
            'balance_due' => 0,
            'currency' => 'USD',
            'completed_at' => now(),
        ]);

        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'sku' => $this->variant->sku,
            'name' => 'Widget',
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total_inc_tax' => $lineTotal,
        ]);

        SalePayment::query()->create([
            'sale_id' => $sale->id,
            'cashier_id' => $this->cashier->id,
            'method' => PaymentMethod::Cash,
            'amount' => $lineTotal,
            'status' => PaymentStatus::Completed,
            'created_at' => now(),
        ]);

        return $sale->fresh(['items', 'payments']);
    }
}
