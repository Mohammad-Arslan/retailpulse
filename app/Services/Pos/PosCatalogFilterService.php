<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Support\Pos\PosSellableProducts;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branch-aware POS filter facets (categories / brands with sellable stock).
 */
final class PosCatalogFilterService
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public function categoriesForBranch(?int $branchId): array
    {
        if ($branchId === null || $branchId <= 0) {
            return Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
                ->all();
        }

        $ids = $this->sellableVariantQuery($branchId)
            ->select('products.category_id')
            ->whereNotNull('products.category_id')
            ->distinct()
            ->pluck('products.category_id')
            ->all();

        if ($ids === []) {
            return [];
        }

        return Category::query()
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function brandsForBranch(?int $branchId): array
    {
        if ($branchId === null || $branchId <= 0) {
            return Brand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name])
                ->all();
        }

        $ids = $this->sellableVariantQuery($branchId)
            ->select('products.brand_id')
            ->whereNotNull('products.brand_id')
            ->distinct()
            ->pluck('products.brand_id')
            ->all();

        if ($ids === []) {
            return [];
        }

        return Brand::query()
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name])
            ->all();
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function sellableVariantQuery(int $branchId): Builder
    {
        $query = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.is_active', true);

        PosSellableProducts::restrictToInStock($query, $branchId);

        return $query;
    }
}
