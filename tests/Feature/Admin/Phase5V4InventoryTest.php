<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\DTOs\BinLocation\BinTransferData;
use App\DTOs\CountSession\CreateCountSessionData;
use App\DTOs\CountSession\SubmitCountLinesData;
use App\Enums\CountScopeType;
use App\Enums\ProductType;
use App\Enums\StockMovementReason;
use App\Events\LowStockAlert;
use App\Models\BinLocation;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantBranchSetting;
use App\Models\Warehouse;
use App\Models\WarehouseZone;
use App\Services\BinLocationService;
use App\Services\CountSessionService;
use App\Services\InventoryService;
use App\Services\QuarantineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class Phase5V4InventoryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Test Branch',
            'code' => 'TST',
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
            'reorder_point' => 10,
            'is_default' => true,
        ]);
    }

    public function test_bin_crud_and_transfer_moves_stock_between_bins(): void
    {
        $zone = WarehouseZone::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'name' => 'A Zone',
            'code' => 'A',
            'is_active' => true,
        ]);

        $binA = BinLocation::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'warehouse_zone_id' => $zone->id,
            'bin_code' => 'A-01',
            'is_active' => true,
        ]);

        $binB = BinLocation::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'warehouse_zone_id' => $zone->id,
            'bin_code' => 'A-02',
            'is_active' => true,
        ]);

        app(InventoryService::class)->setOpeningBalance(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 20,
            binLocationId: $binA->id,
        );

        app(BinLocationService::class)->transfer(new BinTransferData(
            warehouseId: $this->warehouse->id,
            fromBinId: $binA->id,
            toBinId: $binB->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 8,
            userId: null,
        ));

        $this->assertDatabaseHas('inventories', [
            'bin_location_id' => $binA->id,
            'quantity_on_hand' => 12,
        ]);
        $this->assertDatabaseHas('inventories', [
            'bin_location_id' => $binB->id,
            'quantity_on_hand' => 8,
        ]);

        $total = (int) Inventory::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->sum('quantity_on_hand');

        $this->assertSame(20, $total);
    }

    public function test_quarantine_excluded_from_available_stock(): void
    {
        Inventory::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => 15,
            'quantity_reserved' => 0,
            'quantity_in_quarantine' => 0,
        ]);

        $quarantine = app(QuarantineService::class);
        $quarantine->addToQuarantine(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            binLocationId: null,
            quantity: 5,
        );

        $inventory = Inventory::query()->first();
        $this->assertSame(10, $inventory->quantity_on_hand);
        $this->assertSame(5, $inventory->quantity_in_quarantine);
        $this->assertSame(5, $inventory->availableQuantity());

        $check = app(InventoryService::class)->checkAvailability($this->warehouse->id, [
            ['variant_id' => $this->variant->id, 'batch_id' => null, 'quantity' => 11],
        ]);

        $this->assertFalse($check[0]['can_sell']);
    }

    public function test_count_session_blind_mode_hides_system_qty_until_submitted(): void
    {
        Inventory::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => 12,
            'quantity_reserved' => 0,
            'quantity_in_quarantine' => 0,
        ]);

        $admin = User::factory()->create(['is_active' => true]);

        $service = app(CountSessionService::class);
        $session = $service->create(new CreateCountSessionData(
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            scopeType: CountScopeType::Full,
            scopeId: null,
            blindCount: true,
            freezeMode: false,
            varianceThresholdPct: null,
            varianceThresholdValue: null,
            userId: $admin->id,
        ));

        $session = $service->start($session);
        $line = $session->lines->first();
        $this->assertNotNull($line);

        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get(route('admin.count-sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/CountSessions/Show')
                ->where('session.lines.0.system_qty', null)
            );
    }

    public function test_posted_count_creates_cycle_count_adjustment(): void
    {
        Inventory::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'quantity_in_quarantine' => 0,
        ]);

        $admin = User::factory()->create(['is_active' => true]);

        $service = app(CountSessionService::class);
        $session = $service->create(new CreateCountSessionData(
            branchId: $this->branch->id,
            warehouseId: $this->warehouse->id,
            scopeType: CountScopeType::Full,
            scopeId: null,
            blindCount: false,
            freezeMode: false,
            varianceThresholdPct: null,
            varianceThresholdValue: null,
            userId: $admin->id,
        ));

        $session = $service->start($session);
        $line = $session->lines->first();

        $service->submitCounts($session, new SubmitCountLinesData(
            lines: [['line_id' => $line->id, 'counted_qty' => 8]],
            userId: $admin->id,
        ));

        $session = $session->fresh();
        $service->approve($session, $admin->id);
        $service->post($session->fresh(), $admin->id);

        $this->assertDatabaseHas('stock_movements', [
            'reason' => 'cycle_count_adjustment',
            'qty_delta' => -2,
        ]);

        $this->assertSame(8, Inventory::query()->first()?->quantity_on_hand);
    }

    public function test_low_stock_alert_dispatched_when_reorder_point_breached(): void
    {
        Event::fake([LowStockAlert::class]);

        VariantBranchSetting::query()->create([
            'branch_id' => $this->branch->id,
            'product_variant_id' => $this->variant->id,
            'reorder_point' => 10,
            'safety_stock_qty' => 5,
        ]);

        $inventory = Inventory::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => 15,
            'quantity_reserved' => 0,
            'quantity_in_quarantine' => 0,
        ]);

        app(InventoryService::class)->applyDelta(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            qtyDelta: -6,
            reason: StockMovementReason::Adjustment,
        );

        Event::assertDispatched(LowStockAlert::class);
    }

    public function test_opening_stock_import_with_bin_code_sets_per_bin_on_hand(): void
    {
        $bin = BinLocation::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'bin_code' => 'BIN-A1',
            'is_active' => true,
        ]);

        app(InventoryService::class)->setOpeningBalance(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 25,
            binLocationId: $bin->id,
        );

        $this->assertDatabaseHas('inventories', [
            'bin_location_id' => $bin->id,
            'quantity_on_hand' => 25,
        ]);
    }
}
