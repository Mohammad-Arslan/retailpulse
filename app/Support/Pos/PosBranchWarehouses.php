<?php

declare(strict_types=1);

namespace App\Support\Pos;

use App\Models\Inventory;
use App\Models\Warehouse;

/**
 * Resolves active warehouses and available stock for a branch (POS sells from any branch warehouse).
 */
final class PosBranchWarehouses
{
    /**
     * @return list<int>
     */
    public static function activeIds(int $branchId): array
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function defaultId(int $branchId): ?int
    {
        $warehouse = Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first()
            ?? Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->first();

        return $warehouse?->id;
    }

    public static function totalAvailable(int $branchId, int $variantId): int
    {
        $warehouseIds = self::activeIds($branchId);

        if ($warehouseIds === []) {
            return 0;
        }

        return (int) Inventory::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('product_variant_id', $variantId)
            ->get()
            ->sum(fn (Inventory $inventory) => $inventory->availableQuantity());
    }

    /**
     * Pick a warehouse with enough sellable stock, preferring the branch default.
     */
    public static function resolveForVariant(int $branchId, int $variantId, int $quantity = 1): ?int
    {
        $warehouseIds = self::activeIds($branchId);

        if ($warehouseIds === []) {
            return null;
        }

        $defaultId = self::defaultId($branchId);

        if ($defaultId !== null && self::availableAt($defaultId, $variantId) >= $quantity) {
            return $defaultId;
        }

        foreach ($warehouseIds as $warehouseId) {
            if (self::availableAt($warehouseId, $variantId) >= $quantity) {
                return $warehouseId;
            }
        }

        return null;
    }

    public static function availableAt(int $warehouseId, int $variantId): int
    {
        return (int) Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->get()
            ->sum(fn (Inventory $inventory) => $inventory->availableQuantity());
    }
}
