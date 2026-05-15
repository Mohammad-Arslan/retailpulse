<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Inventory;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class InventoryRepository implements InventoryRepositoryInterface
{
    public function findForUpdate(int $warehouseId, int $variantId, ?int $batchId = null): ?Inventory
    {
        return $this->baseQuery($warehouseId, $variantId, $batchId)
            ->lockForUpdate()
            ->first();
    }

    public function lockOrCreate(int $warehouseId, int $variantId, ?int $batchId = null): Inventory
    {
        $existing = $this->findForUpdate($warehouseId, $variantId, $batchId);

        if ($existing !== null) {
            return $existing;
        }

        return Inventory::query()->create([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'batch_id' => $batchId,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);
    }

    public function paginateByWarehouse(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Inventory::query()
            ->with([
                'warehouse.branch',
                'variant.product',
                'batch',
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
                    $q->whereHas('variant', function ($variant) use ($term) {
                        $variant->where('sku', 'like', $term)
                            ->orWhere('barcode', 'like', $term)
                            ->orWhere('name', 'like', $term)
                            ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $term));
                    });
                },
            )
            ->when(
                $filters['low_stock'] ?? false,
                fn ($q) => $q->whereRaw('quantity_on_hand <= quantity_reserved'),
            );

        $sort = $filters['sort'] ?? 'product_name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'on_hand') {
            $query->orderBy('quantity_on_hand', $direction);
        } elseif ($sort === 'available') {
            $query->orderByRaw('(quantity_on_hand - quantity_reserved) '.$direction);
        } else {
            $query->join('product_variants', 'inventories.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->orderBy('products.name', $direction)
                ->select('inventories.*');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function availableQuantity(int $warehouseId, int $variantId, ?int $batchId = null): int
    {
        $inventory = $this->baseQuery($warehouseId, $variantId, $batchId)->first();

        return $inventory?->availableQuantity() ?? 0;
    }

    private function baseQuery(int $warehouseId, int $variantId, ?int $batchId)
    {
        return Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->when(
                $batchId === null,
                fn ($q) => $q->whereNull('batch_id'),
                fn ($q) => $q->where('batch_id', $batchId),
            );
    }
}
