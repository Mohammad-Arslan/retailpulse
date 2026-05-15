<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventoryRepositoryInterface
{
    public function findForUpdate(int $warehouseId, int $variantId, ?int $batchId = null): ?Inventory;

    public function lockOrCreate(int $warehouseId, int $variantId, ?int $batchId = null): Inventory;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateByWarehouse(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function availableQuantity(int $warehouseId, int $variantId, ?int $batchId = null): int;
}
