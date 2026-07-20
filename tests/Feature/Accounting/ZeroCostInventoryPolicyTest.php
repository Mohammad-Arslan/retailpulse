<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\InventoryCostLayer;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class ZeroCostInventoryPolicyTest extends TestCase
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
            'name' => 'Zero Cost Branch',
            'code' => 'ZCB',
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
            'name' => 'Zero Cost Widget',
            'slug' => 'zero-cost-widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'ZC-WDG',
            'sell_price' => 50,
            'is_default' => true,
        ]);

        $this->cashier = User::factory()->create(['is_active' => true]);
    }

    private function setPolicy(string $policy): void
    {
        app(FinancialSettingsService::class)->get()->update(['zero_cost_inventory_policy' => $policy]);
    }

    public function test_zero_cost_receipt_is_blocked_under_block_policy(): void
    {
        $this->setPolicy('block');

        $this->expectException(ValidationException::class);

        app(CostService::class)->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 0,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 1,
        );
    }

    public function test_zero_cost_receipt_is_flagged_and_logged_under_warn_policy(): void
    {
        $this->setPolicy('warn');

        Log::shouldReceive('warning')->once();

        $layer = app(CostService::class)->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 0,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 2,
        );

        $this->assertTrue($layer->is_zero_cost);
    }

    public function test_zero_cost_receipt_is_created_without_warning_under_allow_policy(): void
    {
        $this->setPolicy('allow');

        Log::shouldReceive('warning')->never();

        $layer = app(CostService::class)->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 0,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 3,
        );

        $this->assertTrue($layer->is_zero_cost);
        $this->assertSame(1, InventoryCostLayer::query()->count());
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
            'name' => 'Zero Cost Widget',
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

    public function test_sale_with_no_cost_history_is_blocked_under_block_policy(): void
    {
        app(FinancialSettingsService::class)->get()->update(['negative_inventory_policy' => 'allow']);
        $this->setPolicy('block');

        $sale = $this->createSaleWithItem(quantity: 2, unitPrice: 50);

        $this->expectException(ValidationException::class);

        app(CostService::class)->consumeOnSale($sale->items()->first());
    }

    public function test_sale_with_no_cost_history_proceeds_and_logs_under_warn_policy(): void
    {
        app(FinancialSettingsService::class)->get()->update(['negative_inventory_policy' => 'allow']);
        $this->setPolicy('warn');

        Log::shouldReceive('warning')->once();

        $sale = $this->createSaleWithItem(quantity: 2, unitPrice: 50);
        $consumed = app(CostService::class)->consumeOnSale($sale->items()->first());

        $this->assertSame(0.0, $consumed->amount);
        $this->assertSame('none', $consumed->basis);
    }

    public function test_sale_with_no_cost_history_proceeds_silently_under_allow_policy(): void
    {
        app(FinancialSettingsService::class)->get()->update(['negative_inventory_policy' => 'allow']);
        $this->setPolicy('allow');

        $sale = $this->createSaleWithItem(quantity: 2, unitPrice: 50);
        $consumed = app(CostService::class)->consumeOnSale($sale->items()->first());

        $this->assertSame(0.0, $consumed->amount);
        $this->assertTrue($consumed->estimated);
        $this->assertSame('none', $consumed->basis);
    }
}
