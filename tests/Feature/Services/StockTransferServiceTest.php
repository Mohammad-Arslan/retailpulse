<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DTOs\StockTransfer\CreateStockTransferData;
use App\DTOs\StockTransfer\ReceiveStockTransferData;
use App\DTOs\StockTransfer\ReceiveTransferLineData;
use App\DTOs\StockTransfer\TransferLineData;
use App\Enums\ProductType;
use App\Enums\StockMovementReason;
use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class StockTransferServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Warehouse $fromWarehouse;

    private Warehouse $toWarehouse;

    private ProductVariant $variant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $branch = Branch::query()->create([
            'name' => 'Test Branch',
            'code' => 'TST',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->fromWarehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Source',
            'code' => 'SRC',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->toWarehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Destination',
            'code' => 'DST',
            'is_default' => false,
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

        $this->user = User::factory()->create(['is_active' => true]);

        app(InventoryService::class)->setOpeningBalance(
            $this->fromWarehouse->id,
            $this->variant->id,
            null,
            50,
        );
    }

    public function test_create_rejects_same_source_and_destination_warehouse(): void
    {
        $service = app(StockTransferService::class);

        $this->expectException(ValidationException::class);

        $service->create(new CreateStockTransferData(
            fromWarehouseId: $this->fromWarehouse->id,
            toWarehouseId: $this->fromWarehouse->id,
            lines: [
                new TransferLineData(
                    variantId: $this->variant->id,
                    batchId: null,
                    quantity: 5,
                ),
            ],
            userId: $this->user->id,
            notes: null,
        ));
    }

    public function test_ship_validates_availability_before_deducting(): void
    {
        $service = app(StockTransferService::class);
        $inventory = app(InventoryService::class);

        $transfer = $service->create(new CreateStockTransferData(
            fromWarehouseId: $this->fromWarehouse->id,
            toWarehouseId: $this->toWarehouse->id,
            lines: [
                new TransferLineData(
                    variantId: $this->variant->id,
                    batchId: null,
                    quantity: 20,
                ),
            ],
            userId: $this->user->id,
            notes: null,
        ));

        $inventory->applyDelta(
            warehouseId: $this->fromWarehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            qtyDelta: -35,
            reason: StockMovementReason::Adjustment,
        );

        $this->expectException(ValidationException::class);

        $service->ship($transfer, $this->user->id);
    }

    public function test_partial_receive_sets_partially_received_status(): void
    {
        $service = app(StockTransferService::class);

        $transfer = $service->create(new CreateStockTransferData(
            fromWarehouseId: $this->fromWarehouse->id,
            toWarehouseId: $this->toWarehouse->id,
            lines: [
                new TransferLineData(
                    variantId: $this->variant->id,
                    batchId: null,
                    quantity: 20,
                ),
            ],
            userId: $this->user->id,
            notes: null,
        ));

        $service->ship($transfer, $this->user->id);
        $transfer->refresh();

        $item = $transfer->items->first();
        $this->assertNotNull($item);

        $received = $service->receive($transfer, new ReceiveStockTransferData(
            transferId: $transfer->id,
            userId: $this->user->id,
            lines: [
                new ReceiveTransferLineData(
                    itemId: $item->id,
                    quantity: 8,
                ),
            ],
        ));

        $this->assertSame(StockTransferStatus::PartiallyReceived, $received->status);
        $this->assertNull($received->received_at);
        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $this->toWarehouse->id,
            'quantity_on_hand' => 8,
        ]);
    }

    public function test_full_receive_sets_received_status_and_timestamp(): void
    {
        $service = app(StockTransferService::class);

        $transfer = $service->create(new CreateStockTransferData(
            fromWarehouseId: $this->fromWarehouse->id,
            toWarehouseId: $this->toWarehouse->id,
            lines: [
                new TransferLineData(
                    variantId: $this->variant->id,
                    batchId: null,
                    quantity: 12,
                ),
            ],
            userId: $this->user->id,
            notes: null,
        ));

        $service->ship($transfer, $this->user->id);
        $transfer->refresh();

        $received = $service->receive($transfer, new ReceiveStockTransferData(
            transferId: $transfer->id,
            userId: $this->user->id,
            lines: [],
        ));

        $this->assertSame(StockTransferStatus::Received, $received->status);
        $this->assertNotNull($received->received_at);
        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $this->toWarehouse->id,
            'quantity_on_hand' => 12,
        ]);
    }
}
