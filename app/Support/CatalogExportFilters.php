<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class CatalogExportFilters
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function normalize(array $filters): array
    {
        $normalized = [];

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_values(array_unique(array_filter(array_map(
                static fn (mixed $id): int => (int) $id,
                $filters['ids'],
            ))));

            if ($ids !== []) {
                $normalized['ids'] = $ids;
            }
        }

        if (! empty($filters['search'])) {
            $normalized['search'] = trim((string) $filters['search']);
        }

        if (! empty($filters['type'])) {
            $normalized['type'] = (string) $filters['type'];
        }

        if (! empty($filters['category_id'])) {
            $normalized['category_id'] = (int) $filters['category_id'];
        }

        if (! empty($filters['brand_id'])) {
            $normalized['brand_id'] = (int) $filters['brand_id'];
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $normalized['is_active'] = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized['is_active'] === null) {
                unset($normalized['is_active']);
            }
        }

        return $normalized;
    }

    /**
     * @param  Builder<\App\Models\ProductVariant>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyProductVariantFilters(Builder $query, array $filters): void
    {
        $filters = self::normalize($filters);

        if (isset($filters['ids'])) {
            $query->whereIn('product_id', $filters['ids']);
        }

        if (isset($filters['category_id'])) {
            $query->whereHas(
                'product',
                fn (Builder $productQuery) => $productQuery->where('category_id', $filters['category_id']),
            );
        }

        if (isset($filters['brand_id'])) {
            $query->whereHas(
                'product',
                fn (Builder $productQuery) => $productQuery->where('brand_id', $filters['brand_id']),
            );
        }

        if (isset($filters['type'])) {
            $query->whereHas(
                'product',
                fn (Builder $productQuery) => $productQuery->where('type', $filters['type']),
            );
        }

        if (isset($filters['is_active'])) {
            $query->whereHas(
                'product',
                fn (Builder $productQuery) => $productQuery->where('is_active', $filters['is_active']),
            );
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $variantQuery) use ($search): void {
                $variantQuery
                    ->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas(
                        'product',
                        fn (Builder $productQuery) => $productQuery->where('name', 'like', "%{$search}%"),
                    );
            });
        }
    }

    /**
     * @param  Builder<\App\Models\Category>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyCategoryFilters(Builder $query, array $filters): void
    {
        $filters = self::normalize($filters);

        if (isset($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $categoryQuery) use ($search): void {
                $categoryQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }
    }

    /**
     * @param  Builder<\App\Models\Brand>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyBrandFilters(Builder $query, array $filters): void
    {
        $filters = self::normalize($filters);

        if (isset($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $brandQuery) use ($search): void {
                $brandQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }
    }
}
