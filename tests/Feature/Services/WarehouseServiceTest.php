<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DTOs\Warehouse\CreateWarehouseData;
use App\Enums\ProductType;
use App\Enums\StockTransferStatus;
use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class WarehouseServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private Warehouse $defaultWarehouse;

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

        $this->defaultWarehouse = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_deactivate_rejects_only_active_warehouse_for_branch(): void
    {
        $service = app(WarehouseService::class);

        $this->expectException(ValidationException::class);

        $service->deactivate($this->defaultWarehouse);
    }

    public function test_deactivate_rejects_warehouse_with_on_hand_stock(): void
    {
        $service = app(WarehouseService::class);

        $overflow = $service->create(new CreateWarehouseData(
            branchId: $this->branch->id,
            name: 'Overflow',
            type: WarehouseType::Backroom,
            isDefault: false,
        ));

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

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'WDG-001',
            'sell_price' => 100,
            'is_default' => true,
        ]);

        app(InventoryService::class)->setOpeningBalance($overflow->id, $variant->id, null, 5);

        $this->expectException(ValidationException::class);

        $service->deactivate($overflow);
    }

    public function test_deactivate_rejects_warehouse_with_open_transfers(): void
    {
        $service = app(WarehouseService::class);

        $overflow = $service->create(new CreateWarehouseData(
            branchId: $this->branch->id,
            name: 'Overflow',
            type: WarehouseType::Backroom,
            isDefault: false,
        ));

        $creator = User::factory()->create(['is_active' => true]);

        StockTransfer::query()->create([
            'reference_no' => 'TRF-0001',
            'from_warehouse_id' => $overflow->id,
            'to_warehouse_id' => $this->defaultWarehouse->id,
            'status' => StockTransferStatus::Draft,
            'created_by' => $creator->id,
        ]);

        $this->expectException(ValidationException::class);

        $service->deactivate($overflow);
    }

    public function test_deactivate_reassigns_default_to_successor(): void
    {
        $service = app(WarehouseService::class);

        $overflow = $service->create(new CreateWarehouseData(
            branchId: $this->branch->id,
            name: 'Overflow',
            type: WarehouseType::Backroom,
            isDefault: false,
        ));

        $service->deactivate($this->defaultWarehouse->fresh());

        $overflow->refresh();
        $this->defaultWarehouse->refresh();

        $this->assertFalse($this->defaultWarehouse->is_active);
        $this->assertTrue($overflow->is_default);
    }
}
