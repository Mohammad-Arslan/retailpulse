<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BinLocation\BinTransferData;
use App\DTOs\BinLocation\CreateBinLocationData;
use App\DTOs\BinLocation\CreateWarehouseZoneData;
use App\DTOs\BinLocation\UpdateBinLocationData;
use App\DTOs\BinLocation\UpdateWarehouseZoneData;
use App\Enums\StockMovementReason;
use App\Events\InventoryStockChanged;
use App\Models\BinLocation;
use App\Models\WarehouseZone;
use App\Repositories\Contracts\BinLocationRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Support\InventoryFreezeGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BinLocationService
{
    public function __construct(
        private readonly BinLocationRepositoryInterface $bins,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly StockMovementRepositoryInterface $movements,
    ) {}

    public function createZone(CreateWarehouseZoneData $data): WarehouseZone
    {
        return WarehouseZone::query()->create([
            'warehouse_id' => $data->warehouseId,
            'name' => $data->name,
            'code' => $data->code,
            'is_active' => true,
        ]);
    }

    public function updateZone(WarehouseZone $zone, UpdateWarehouseZoneData $data): WarehouseZone
    {
        $zone->update([
            'name' => $data->name,
            'code' => $data->code,
            'is_active' => $data->isActive,
        ]);

        return $zone->fresh() ?? $zone;
    }

    public function createBin(CreateBinLocationData $data): BinLocation
    {
        if ($this->bins->findByCode($data->warehouseId, $data->binCode) !== null) {
            throw ValidationException::withMessages([
                'bin_code' => __('Bin code already exists in this warehouse.'),
            ]);
        }

        return BinLocation::query()->create([
            'warehouse_id' => $data->warehouseId,
            'warehouse_zone_id' => $data->warehouseZoneId,
            'zone' => $data->zone,
            'aisle' => $data->aisle,
            'shelf' => $data->shelf,
            'bin_code' => $data->binCode,
            'capacity_limit' => $data->capacityLimit,
            'is_active' => true,
        ]);
    }

    public function updateBin(BinLocation $bin, UpdateBinLocationData $data): BinLocation
    {
        $existing = $this->bins->findByCode($bin->warehouse_id, $data->binCode);

        if ($existing !== null && $existing->id !== $bin->id) {
            throw ValidationException::withMessages([
                'bin_code' => __('Bin code already exists in this warehouse.'),
            ]);
        }

        $bin->update([
            'warehouse_zone_id' => $data->warehouseZoneId,
            'zone' => $data->zone,
            'aisle' => $data->aisle,
            'shelf' => $data->shelf,
            'bin_code' => $data->binCode,
            'capacity_limit' => $data->capacityLimit,
            'is_active' => $data->isActive,
        ]);

        return $bin->fresh() ?? $bin;
    }

    public function transfer(BinTransferData $data): void
    {
        if ($data->fromBinId === $data->toBinId) {
            throw ValidationException::withMessages([
                'to_bin_id' => __('Source and destination bins must differ.'),
            ]);
        }

        if ($data->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Transfer quantity must be positive.'),
            ]);
        }

        $fromBin = $this->bins->findById($data->fromBinId);
        $toBin = $this->bins->findById($data->toBinId);

        if ($fromBin === null || $toBin === null) {
            throw ValidationException::withMessages([
                'bin' => __('Bin location not found.'),
            ]);
        }

        if ($fromBin->warehouse_id !== $data->warehouseId || $toBin->warehouse_id !== $data->warehouseId) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('Bins must belong to the same warehouse.'),
            ]);
        }

        InventoryFreezeGuard::assertNotFrozen($data->warehouseId, $fromBin->id);

        DB::transaction(function () use ($data, $fromBin, $toBin): void {
            $source = $this->inventories->lockOrCreate(
                $data->warehouseId,
                $data->variantId,
                $data->batchId,
                $fromBin->id,
            );

            if ($source->availableQuantity() < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient stock in source bin.'),
                ]);
            }

            $dest = $this->inventories->lockOrCreate(
                $data->warehouseId,
                $data->variantId,
                $data->batchId,
                $toBin->id,
            );

            $sourcePrevOnHand = $source->quantity_on_hand;
            $sourcePrevReserved = $source->quantity_reserved;
            $destPrevOnHand = $dest->quantity_on_hand;
            $destPrevReserved = $dest->quantity_reserved;

            $source->decrement('quantity_on_hand', $data->quantity);
            $dest->increment('quantity_on_hand', $data->quantity);

            $source = $source->fresh() ?? $source;
            $dest = $dest->fresh() ?? $dest;

            $this->movements->create([
                'warehouse_id' => $data->warehouseId,
                'product_variant_id' => $data->variantId,
                'batch_id' => $data->batchId,
                'reason' => StockMovementReason::BinTransferOut,
                'qty_delta' => -$data->quantity,
                'quantity_on_hand_after' => $source->quantity_on_hand,
                'user_id' => $data->userId,
                'notes' => $data->notes ?? "Bin transfer out: {$fromBin->bin_code} → {$toBin->bin_code}",
            ]);

            $this->movements->create([
                'warehouse_id' => $data->warehouseId,
                'product_variant_id' => $data->variantId,
                'batch_id' => $data->batchId,
                'reason' => StockMovementReason::BinTransferIn,
                'qty_delta' => $data->quantity,
                'quantity_on_hand_after' => $dest->quantity_on_hand,
                'user_id' => $data->userId,
                'notes' => $data->notes ?? "Bin transfer in: {$fromBin->bin_code} → {$toBin->bin_code}",
            ]);

            event(new InventoryStockChanged($source, $sourcePrevOnHand, $sourcePrevReserved, StockMovementReason::BinTransferOut));
            event(new InventoryStockChanged($dest, $destPrevOnHand, $destPrevReserved, StockMovementReason::BinTransferIn));
        });
    }
}
