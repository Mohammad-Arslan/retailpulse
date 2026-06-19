<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\BinLocation;
use App\Models\WarehouseZone;
use App\Repositories\Contracts\BinLocationRepositoryInterface;
use Illuminate\Support\Collection;

final class BinLocationRepository implements BinLocationRepositoryInterface
{
    public function zonesForWarehouse(int $warehouseId): Collection
    {
        return WarehouseZone::query()
            ->where('warehouse_id', $warehouseId)
            ->orderBy('code')
            ->get();
    }

    public function binsForWarehouse(int $warehouseId, ?int $zoneId = null): Collection
    {
        return BinLocation::query()
            ->with('warehouseZone')
            ->where('warehouse_id', $warehouseId)
            ->when($zoneId !== null, fn ($q) => $q->where('warehouse_zone_id', $zoneId))
            ->orderBy('bin_code')
            ->get();
    }

    public function findByCode(int $warehouseId, string $binCode): ?BinLocation
    {
        return BinLocation::query()
            ->where('warehouse_id', $warehouseId)
            ->where('bin_code', $binCode)
            ->first();
    }

    public function findById(int $id): ?BinLocation
    {
        return BinLocation::query()->find($id);
    }
}
