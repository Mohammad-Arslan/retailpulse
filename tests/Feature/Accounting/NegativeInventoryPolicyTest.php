<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\DTOs\Inventory\DeductStockData;
use App\Enums\ProductType;
use App\Enums\StockMovementReason;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\FinancialSettingsService;
use App\Services\InventoryService;
use App\Services\PosPinService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsAccounting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class NegativeInventoryPolicyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;
    use SeedsRbac;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();
        $this->seedRbac();

        $branch = Branch::query()->create([
            'name' => 'Negative Inventory Branch',
            'code' => 'NIB',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Negative Inventory Widget',
            'slug' => 'negative-inventory-widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'NEG-WDG',
            'sell_price' => 25,
            'is_default' => true,
        ]);

        $this->cashier = User::factory()->create(['is_active' => true]);

        app(InventoryService::class)->applyDelta(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            qtyDelta: 5,
            reason: StockMovementReason::OpeningBalance,
            userId: $this->cashier->id,
        );
    }

    private function setPolicy(string $policy): void
    {
        app(FinancialSettingsService::class)->get()->update(['negative_inventory_policy' => $policy]);
    }

    private function deductBeyondAvailable(?string $managerPin = null): void
    {
        app(InventoryService::class)->deduct(new DeductStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 10,
            reason: StockMovementReason::Sale,
            userId: $this->cashier->id,
            managerPin: $managerPin,
        ));
    }

    public function test_strict_policy_blocks_sale_that_would_go_negative(): void
    {
        $this->setPolicy('strict');

        $this->expectException(ValidationException::class);

        $this->deductBeyondAvailable();
    }

    public function test_allow_policy_permits_sale_that_would_go_negative(): void
    {
        $this->setPolicy('allow');

        $this->deductBeyondAvailable();

        $inventory = Inventory::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        $this->assertSame(-5, $inventory->quantity_on_hand);
    }

    public function test_approval_required_blocks_without_a_manager_pin(): void
    {
        $this->setPolicy('approval_required');

        try {
            $this->deductBeyondAvailable();
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('manager_approval', $e->errors());
        }
    }

    public function test_approval_required_blocks_with_an_invalid_pin(): void
    {
        $this->setPolicy('approval_required');

        $this->cashier->givePermissionTo('pos.override-stock');
        app(PosPinService::class)->setPin($this->cashier, '654321');

        try {
            $this->deductBeyondAvailable('000000');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('manager_approval', $e->errors());
        }
    }

    public function test_approval_required_blocks_a_valid_pin_without_permission(): void
    {
        $this->setPolicy('approval_required');

        app(PosPinService::class)->setPin($this->cashier, '123456');

        $this->expectException(AuthorizationException::class);

        $this->deductBeyondAvailable('123456');
    }

    public function test_approval_required_succeeds_with_a_valid_pin_and_permission(): void
    {
        $this->setPolicy('approval_required');

        $this->cashier->givePermissionTo('pos.override-stock');
        app(PosPinService::class)->setPin($this->cashier, '123456');

        $inventory = app(InventoryService::class)->deduct(new DeductStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 10,
            reason: StockMovementReason::Sale,
            userId: $this->cashier->id,
            managerPin: '123456',
        ));

        $this->assertSame(-5, $inventory->quantity_on_hand);
    }
}
