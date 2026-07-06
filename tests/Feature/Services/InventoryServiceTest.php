<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DTOs\Inventory\DeductStockData;
use App\DTOs\Inventory\ReserveStockData;
use App\Enums\PickingStrategy;
use App\Enums\ProductType;
use App\Enums\StockMovementReason;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $branch = Branch::query()->create([
            'name' => 'Test Branch',
            'code' => 'TST',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'picking_strategy' => PickingStrategy::Fifo,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'name' => 'Piece',
            'abbreviation' => 'pc',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Widget',
            'slug' => 'widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'WDG-001',
            'sell_price' => 100,
            'is_default' => true,
        ]);
    }

    public function test_apply_delta_increments_on_hand_and_writes_movement(): void
    {
        $service = app(InventoryService::class);

        $inventory = $service->applyDelta(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            qtyDelta: 15,
            reason: StockMovementReason::Adjustment,
            userId: null,
            notes: 'Test adjustment',
        );

        $this->assertSame(15, $inventory->quantity_on_hand);

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'reason' => StockMovementReason::Adjustment->value,
            'qty_delta' => 15,
            'quantity_on_hand_after' => 15,
        ]);
    }

    public function test_apply_delta_rejects_negative_on_hand(): void
    {
        $service = app(InventoryService::class);
        $service->setOpeningBalance($this->warehouse->id, $this->variant->id, null, 5);

        $this->expectException(ValidationException::class);

        $service->applyDelta(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            qtyDelta: -10,
            reason: StockMovementReason::Adjustment,
        );
    }

    public function test_apply_delta_respects_reserved_quantity_floor(): void
    {
        $service = app(InventoryService::class);
        $service->setOpeningBalance($this->warehouse->id, $this->variant->id, null, 20);

        $service->reserve(new ReserveStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 8,
            referenceType: 'test_cart',
            referenceId: 1,
        ));

        $this->expectException(ValidationException::class);

        $service->applyDelta(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            qtyDelta: -15,
            reason: StockMovementReason::Adjustment,
        );
    }

    public function test_deduct_allocates_fifo_across_batches(): void
    {
        $this->assertBatchDeductionOrder(PickingStrategy::Fifo, 'B-OLD', 'B-NEW');
    }

    public function test_deduct_allocates_fefo_by_expiry_date(): void
    {
        $branch = Branch::query()->first();
        $branch?->update(['picking_strategy' => PickingStrategy::Fefo]);

        $this->assertBatchDeductionOrder(PickingStrategy::Fefo, 'B-SOON', 'B-LATE', useExpiry: true);
    }

    private function assertBatchDeductionOrder(
        PickingStrategy $strategy,
        string $firstBatchNumber,
        string $secondBatchNumber,
        bool $useExpiry = false,
    ): void {
        if ($strategy === PickingStrategy::Fefo) {
            $branch = Branch::query()->first();
            $branch?->update(['picking_strategy' => PickingStrategy::Fefo]);
        }

        $unit = Unit::query()->first();
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Batch Widget',
            'slug' => 'batch-widget',
            'unit_id' => $unit->id,
            'track_batches' => true,
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'BAT-001',
            'sell_price' => 50,
            'is_default' => true,
        ]);

        $batchOld = ProductBatch::query()->create([
            'product_variant_id' => $variant->id,
            'batch_no' => $firstBatchNumber,
            'expiry_date' => $useExpiry ? now()->addDays(3) : null,
        ]);

        $batchNew = ProductBatch::query()->create([
            'product_variant_id' => $variant->id,
            'batch_no' => $secondBatchNumber,
            'expiry_date' => $useExpiry ? now()->addDays(30) : null,
        ]);

        $service = app(InventoryService::class);
        $service->setOpeningBalance($this->warehouse->id, $variant->id, $batchOld->id, 5);
        $service->setOpeningBalance($this->warehouse->id, $variant->id, $batchNew->id, 10);

        $service->deduct(new DeductStockData(
            warehouseId: $this->warehouse->id,
            variantId: $variant->id,
            batchId: null,
            quantity: 7,
            reason: StockMovementReason::Sale,
        ));

        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batchOld->id,
            'quantity_on_hand' => 0,
        ]);
        $this->assertDatabaseHas('inventories', [
            'batch_id' => $batchNew->id,
            'quantity_on_hand' => 8,
        ]);
    }

    public function test_deduct_throws_when_insufficient_stock(): void
    {
        $service = app(InventoryService::class);
        $service->setOpeningBalance($this->warehouse->id, $this->variant->id, null, 3);

        $this->expectException(ValidationException::class);

        $service->deduct(new DeductStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 5,
            reason: StockMovementReason::Sale,
        ));
    }

    public function test_deduct_releases_reservation_proportionally_on_sale(): void
    {
        $service = app(InventoryService::class);
        $service->setOpeningBalance($this->warehouse->id, $this->variant->id, null, 20);

        $service->reserve(new ReserveStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 10,
            referenceType: 'pos_cart',
            referenceId: 42,
        ));

        $service->deduct(new DeductStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 6,
            reason: StockMovementReason::Sale,
            referenceType: 'pos_cart',
            referenceId: 42,
        ));

        $inventory = $service->availableQuantity($this->warehouse->id, $this->variant->id);
        $this->assertSame(10, $inventory);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => 14,
            'quantity_reserved' => 4,
        ]);
    }

    public function test_reserve_and_partial_release_reduce_reservation_quantity(): void
    {
        $service = app(InventoryService::class);
        $service->setOpeningBalance($this->warehouse->id, $this->variant->id, null, 20);

        $service->reserve(new ReserveStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 10,
            referenceType: 'hold',
            referenceId: 1,
        ));

        $service->release(new ReserveStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 3,
            referenceType: 'hold',
            referenceId: 1,
        ));

        $reservation = StockReservation::query()
            ->whereNull('released_at')
            ->first();

        $this->assertNotNull($reservation);
        $this->assertSame(7, $reservation->quantity);

        $this->assertDatabaseHas('inventories', [
            'quantity_reserved' => 7,
        ]);
    }

    public function test_release_expired_reservations_via_scheduled_command(): void
    {
        $service = app(InventoryService::class);
        $service->setOpeningBalance($this->warehouse->id, $this->variant->id, null, 20);

        $service->reserve(new ReserveStockData(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 5,
            referenceType: 'cart',
            referenceId: 99,
        ));

        StockReservation::query()->update(['expires_at' => now()->subMinute()]);

        Artisan::call('inventory:release-expired-reservations');

        $this->assertDatabaseHas('inventories', [
            'quantity_reserved' => 0,
        ]);

        $this->assertDatabaseHas('stock_reservations', [
            'reference_id' => 99,
        ]);

        $this->assertNotNull(
            StockReservation::query()->where('reference_id', 99)->value('released_at'),
        );
    }
}
