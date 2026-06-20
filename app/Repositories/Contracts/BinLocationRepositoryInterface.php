<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\BinLocation;
use App\Models\WarehouseZone;
use Illuminate\Support\Collection;

interface BinLocationRepositoryInterface
{
    /**
     * @return Collection<int, WarehouseZone>
     */
    public function zonesForWarehouse(int $warehouseId): Collection;

    /**
     * @return Collection<int, BinLocation>
     */
    public function binsForWarehouse(int $warehouseId, ?int $zoneId = null): Collection;

    public function findByCode(int $warehouseId, string $binCode): ?BinLocation;

    public function findById(int $id): ?BinLocation;
}
