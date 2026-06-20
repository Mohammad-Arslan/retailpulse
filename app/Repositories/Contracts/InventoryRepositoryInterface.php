<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\PickingStrategy;
use App\Models\Inventory;
use Illuminate\Pagination\LengthAwarePaginator;

interface InventoryRepositoryInterface
{
    public function findForUpdate(
        int $warehouseId,
        int $variantId,
        ?int $batchId = null,
        ?int $binLocationId = null,
    ): ?Inventory;

    public function lockOrCreate(
        int $warehouseId,
        int $variantId,
        ?int $batchId = null,
        ?int $binLocationId = null,
    ): Inventory;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateByWarehouse(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateByBin(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function availableQuantity(
        int $warehouseId,
        int $variantId,
        ?int $batchId = null,
        ?int $binLocationId = null,
    ): int;

    public function totalAvailableQuantity(int $warehouseId, int $variantId, ?int $batchId = null): int;

    /**
     * Split a deduction across batch lines using branch picking strategy.
     *
     * @return list<array{batch_id: int|null, quantity: int}>
     */
    public function allocateDeductionLines(
        int $warehouseId,
        int $variantId,
        int $quantity,
        PickingStrategy $strategy,
        bool $trackBatches,
    ): array;
}
