<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Warehouse;

interface WarehouseRepositoryInterface
{
    public function create(array $attributes): Warehouse;

    public function setDefaultForBranch(int $branchId, int $warehouseId): void;

    public function clearDefaultForBranch(int $branchId): void;
}
