<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;

final class WarehouseRepository implements WarehouseRepositoryInterface
{
    public function create(array $attributes): Warehouse
    {
        return Warehouse::query()->create($attributes);
    }

    public function setDefaultForBranch(int $branchId, int $warehouseId): void
    {
        Warehouse::query()
            ->where('branch_id', $branchId)
            ->update(['is_default' => false]);

        Warehouse::query()
            ->whereKey($warehouseId)
            ->where('branch_id', $branchId)
            ->update(['is_default' => true]);
    }

    public function clearDefaultForBranch(int $branchId): void
    {
        Warehouse::query()
            ->where('branch_id', $branchId)
            ->update(['is_default' => false]);
    }
}
