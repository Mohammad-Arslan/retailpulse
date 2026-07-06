<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\PickingStrategy;
use App\Models\Inventory;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class InventoryRepository implements InventoryRepositoryInterface
{
    public function findForUpdate(
        int $warehouseId,
        int $variantId,
        ?int $batchId = null,
        ?int $binLocationId = null,
    ): ?Inventory {
        return $this->baseQuery($warehouseId, $variantId, $batchId, $binLocationId)
            ->lockForUpdate()
            ->first();
    }

    public function lockOrCreate(
        int $warehouseId,
        int $variantId,
        ?int $batchId = null,
        ?int $binLocationId = null,
    ): Inventory {
        $existing = $this->findForUpdate($warehouseId, $variantId, $batchId, $binLocationId);

        if ($existing !== null) {
            return $existing;
        }

        return Inventory::query()->create([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'batch_id' => $batchId,
            'bin_location_id' => $binLocationId,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'quantity_in_quarantine' => 0,
        ]);
    }

    public function paginateByWarehouse(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $availableExpr = '(inventories.quantity_on_hand - inventories.quantity_reserved - inventories.quantity_in_quarantine)';

        $query = Inventory::query()
            ->with([
                'warehouse.branch',
                'variant.product',
                'batch',
                'binLocation',
            ])
            ->when(
                $filters['warehouse_id'] ?? null,
                fn ($q, $warehouseId) => $q->where('warehouse_id', $warehouseId),
            )
            ->when(
                $filters['branch_id'] ?? null,
                fn ($q, $branchId) => $q->whereHas(
                    'warehouse',
                    fn ($w) => $w->where('branch_id', $branchId),
                ),
            )
            ->when(
                $filters['search'] ?? null,
                function ($q, string $search) {
                    $term = '%'.addcslashes($search, '%_\\').'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->whereHas('variant', function ($variant) use ($term) {
                            $variant->where('sku', 'like', $term)
                                ->orWhere('barcode', 'like', $term)
                                ->orWhere('name', 'like', $term)
                                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $term));
                        })->orWhereHas('batch', fn ($batch) => $batch->where('batch_no', 'like', $term))
                            ->orWhereHas('binLocation', fn ($bin) => $bin->where('bin_code', 'like', $term));
                    });
                },
            )
            ->when(
                ($filters['availability'] ?? '') === 'in_stock',
                fn ($q) => $q->whereRaw("{$availableExpr} > 0"),
            )
            ->when(
                ($filters['availability'] ?? '') === 'out_of_stock',
                fn ($q) => $q->whereRaw("{$availableExpr} <= 0"),
            )
            ->when(
                ($filters['availability'] ?? '') === 'reserved',
                fn ($q) => $q->where('quantity_reserved', '>', 0),
            )
            ->when(
                ($filters['availability'] ?? '') === 'low_stock',
                fn ($q) => $q->whereHas('variant', function ($variant) use ($availableExpr) {
                    $variant->whereNotNull('reorder_point')
                        ->whereRaw("GREATEST(0, {$availableExpr}) <= product_variants.reorder_point");
                }),
            )
            ->when(
                ($filters['quarantine'] ?? '') === 'yes',
                fn ($q) => $q->where('quantity_in_quarantine', '>', 0),
            )
            ->when(
                ($filters['quarantine'] ?? '') === 'no',
                fn ($q) => $q->where('quantity_in_quarantine', '=', 0),
            )
            ->when(
                ($filters['batch'] ?? '') === 'yes',
                fn ($q) => $q->whereNotNull('batch_id'),
            )
            ->when(
                ($filters['batch'] ?? '') === 'no',
                fn ($q) => $q->whereNull('batch_id'),
            )
            ->when(
                ($filters['bin'] ?? '') === 'assigned',
                fn ($q) => $q->whereNotNull('bin_location_id'),
            )
            ->when(
                ($filters['bin'] ?? '') === 'unassigned',
                fn ($q) => $q->whereNull('bin_location_id'),
            );

        $sort = $filters['sort'] ?? 'product_name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'on_hand') {
            $query->orderBy('quantity_on_hand', $direction);
        } elseif ($sort === 'available') {
            $query->orderByRaw('(quantity_on_hand - quantity_reserved - quantity_in_quarantine) '.$direction);
        } else {
            $query->join('product_variants', 'inventories.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->orderBy('products.name', $direction)
                ->select('inventories.*');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginateByBin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Inventory::query()
            ->with([
                'warehouse.branch',
                'variant.product',
                'batch',
                'binLocation.warehouseZone',
            ])
            ->whereNotNull('inventories.bin_location_id')
            ->when(
                $filters['warehouse_id'] ?? null,
                fn ($q, $warehouseId) => $q->where('inventories.warehouse_id', $warehouseId),
            )
            ->when(
                $filters['zone_id'] ?? null,
                fn ($q, $zoneId) => $q->whereHas(
                    'binLocation',
                    fn ($b) => $b->where('warehouse_zone_id', $zoneId),
                ),
            )
            ->when(
                $filters['branch_id'] ?? null,
                fn ($q, $branchId) => $q->whereHas(
                    'warehouse',
                    fn ($w) => $w->where('branch_id', $branchId),
                ),
            )
            ->when(
                $filters['search'] ?? null,
                function ($q, string $search) {
                    $term = '%'.addcslashes($search, '%_\\').'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->whereHas('variant', function ($variant) use ($term) {
                            $variant->where('sku', 'like', $term)
                                ->orWhere('name', 'like', $term)
                                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $term));
                        })->orWhereHas('binLocation', fn ($b) => $b->where('bin_code', 'like', $term));
                    });
                },
            );

        $sort = $filters['sort'] ?? 'bin_code';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'on_hand') {
            $query->orderBy('inventories.quantity_on_hand', $direction);
        } else {
            $query->join('bin_locations', 'inventories.bin_location_id', '=', 'bin_locations.id')
                ->orderBy('bin_locations.bin_code', $direction)
                ->select('inventories.*');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function availableQuantity(
        int $warehouseId,
        int $variantId,
        ?int $batchId = null,
        ?int $binLocationId = null,
    ): int {
        if ($binLocationId !== null) {
            $inventory = $this->baseQuery($warehouseId, $variantId, $batchId, $binLocationId)->first();

            return $inventory?->availableQuantity() ?? 0;
        }

        return $this->totalAvailableQuantity($warehouseId, $variantId, $batchId);
    }

    public function totalAvailableQuantity(int $warehouseId, int $variantId, ?int $batchId = null): int
    {
        $query = Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId);

        if ($batchId === null) {
            $query->whereNull('batch_id');
        } else {
            $query->where('batch_id', $batchId);
        }

        return (int) $query->get()->sum(fn (Inventory $row) => $row->availableQuantity());
    }

    public function allocateDeductionLines(
        int $warehouseId,
        int $variantId,
        int $quantity,
        PickingStrategy $strategy,
        bool $trackBatches,
    ): array {
        if ($quantity <= 0) {
            return [];
        }

        if (! $trackBatches) {
            return [['batch_id' => null, 'quantity' => $quantity]];
        }

        $query = Inventory::query()
            ->where('inventories.warehouse_id', $warehouseId)
            ->where('inventories.product_variant_id', $variantId)
            ->whereRaw('inventories.quantity_on_hand > inventories.quantity_reserved')
            ->select('inventories.*');

        if ($strategy === PickingStrategy::Fefo) {
            $query
                ->leftJoin('product_batches', 'inventories.batch_id', '=', 'product_batches.id')
                ->orderByRaw('CASE WHEN product_batches.expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('product_batches.expiry_date')
                ->orderBy('inventories.id');
        } else {
            $query->orderBy('inventories.created_at')->orderBy('inventories.id');
        }

        $lines = [];
        $remaining = $quantity;

        foreach ($query->get() as $inventory) {
            $available = $inventory->availableQuantity();

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $lines[] = [
                'batch_id' => $inventory->batch_id,
                'quantity' => $take,
            ];
            $remaining -= $take;

            if ($remaining <= 0) {
                break;
            }
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient stock on hand.'),
            ]);
        }

        return $lines;
    }

    private function baseQuery(int $warehouseId, int $variantId, ?int $batchId, ?int $binLocationId = null)
    {
        return Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->when(
                $batchId === null,
                fn ($q) => $q->whereNull('batch_id'),
                fn ($q) => $q->where('batch_id', $batchId),
            )
            ->when(
                $binLocationId === null,
                fn ($q) => $q->whereNull('bin_location_id'),
                fn ($q) => $q->where('bin_location_id', $binLocationId),
            );
    }
}
