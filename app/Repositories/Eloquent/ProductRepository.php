<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ProductRepository implements ProductRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'variants' => fn ($q) => $q->where('is_default', true),
            ])
            ->withCount('variants');

        $sort = $filters['sort'] ?? 'name';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'type', 'created_at', 'is_active'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): ?Product
    {
        return Product::query()->find($id);
    }

    public function findByIdWithRelations(int $id): ?Product
    {
        return Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'unit:id,name,abbreviation',
                'variants.bundleItems.childVariant.product:id,name,type',
                'variants.branchPrices.branch:id,name',
                'images',
            ])
            ->find($id);
    }

    public function create(array $attributes): Product
    {
        return Product::query()->create($attributes);
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        Product::destroy($product->getKey());
    }

    public function searchVariants(string $term, ?int $excludeProductId = null, int $limit = 20): Collection
    {
        $query = ProductVariant::query()
            ->with('product:id,name,type')
            ->whereHas('product', function ($q) use ($excludeProductId) {
                $q->where('is_active', true)
                    ->where('type', '!=', 'combo');

                if ($excludeProductId !== null) {
                    $q->where('id', '!=', $excludeProductId);
                }
            });

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$term}%"));
            });
        }

        return $query
            ->orderBy('sku')
            ->limit($limit)
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->displayName(),
                'product_name' => $variant->product?->name ?? '',
                'sell_price' => (string) $variant->sell_price,
                'track_serials' => (bool) ($variant->product?->track_serials ?? false),
            ]);
    }
}
