<?php

declare(strict_types=1);

namespace App\Support\Pos;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Restricts POS catalog/search queries to variants that can be sold:
 * inventory-tracked products with available stock at any branch warehouse,
 * or non-tracked types (service/digital).
 */
final class PosSellableProducts
{
    public static function restrictToInStock(Builder $query, int $branchId): Builder
    {
        $warehouseIds = PosBranchWarehouses::activeIds($branchId);

        return $query->where(function (Builder $outer) use ($warehouseIds) {
            $outer->whereIn('products.type', [
                ProductType::Service->value,
                ProductType::Digital->value,
            ]);

            if ($warehouseIds === []) {
                return;
            }

            $outer->orWhereIn('product_variants.id', function (QueryBuilder $sub) use ($warehouseIds) {
                $sub->select('product_variant_id')
                    ->from('inventories')
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->groupBy('product_variant_id')
                    ->havingRaw('SUM(quantity_on_hand - quantity_reserved - COALESCE(quantity_in_quarantine, 0)) > 0');
            });
        });
    }
}
