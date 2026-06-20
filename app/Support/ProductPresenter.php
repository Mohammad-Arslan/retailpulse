<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

final class ProductPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forDetail(Product $product): array
    {
        $product->loadMissing([
            'category:id,name',
            'brand:id,name',
            'unit:id,name,abbreviation',
            'variants.preferredSupplier:id,name,code',
            'variants.bundleItems.childVariant.product:id,name',
            'variants.branchPrices.branch:id,name',
            'images',
        ]);

        $defaultVariant = $product->variants->firstWhere('is_default', true)
            ?? $product->variants->first();

        return [
            'id' => $product->id,
            'type' => $product->type->value,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'is_active' => $product->is_active,
            'track_batches' => $product->track_batches,
            'track_serials' => $product->track_serials,
            'category' => $product->category?->only('id', 'name'),
            'brand' => $product->brand?->only('id', 'name'),
            'unit' => $product->unit?->only('id', 'name', 'abbreviation'),
            'variant_attributes' => $product->variant_attributes ?? [],
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'name' => $v->displayName(),
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'cost_price' => (string) $v->cost_price,
                'sell_price' => (string) $v->sell_price,
                'reorder_point' => $v->reorder_point,
                'preferred_supplier' => $v->preferredSupplier?->only('id', 'name', 'code'),
                'alternate_suppliers' => $v->alternateSuppliers()->map(
                    fn ($supplier) => $supplier->only('id', 'name', 'code'),
                )->values()->all(),
                'attributes' => $v->attributes ?? [],
                'is_default' => $v->is_default,
            ])->values()->all(),
            'bundle_items' => $defaultVariant
                ?->bundleItems
                ->map(fn ($item) => [
                    'quantity' => (string) $item->quantity,
                    'child' => [
                        'id' => $item->childVariant?->id,
                        'sku' => $item->childVariant?->sku,
                        'name' => $item->childVariant?->displayName(),
                        'product_name' => $item->childVariant?->product?->name,
                    ],
                ])
                ->values()
                ->all() ?? [],
            'branch_prices' => $defaultVariant
                ?->branchPrices
                ->map(fn ($price) => [
                    'branch_id' => $price->branch_id,
                    'branch_name' => $price->branch?->name,
                    'sell_price' => (string) $price->sell_price,
                ])
                ->values()
                ->all() ?? [],
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'images' => ImagePresenter::collection($product->images),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(Product $product): array
    {
        $product->loadMissing([
            'category:id,name',
            'brand:id,name',
            'unit:id,name,abbreviation',
            'variants.preferredSupplier:id,name,code',
            'variants.bundleItems.childVariant.product:id,name',
            'variants.branchPrices.branch:id,name',
            'images',
        ]);

        return [
            'id' => $product->id,
            'type' => $product->type->value,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'unit_id' => $product->unit_id,
            'track_batches' => $product->track_batches,
            'track_serials' => $product->track_serials,
            'is_active' => $product->is_active,
            'variant_attributes' => $product->variant_attributes ?? [],
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'cost_price' => (string) $v->cost_price,
                'sell_price' => (string) $v->sell_price,
                'reorder_point' => $v->reorder_point !== null ? (string) $v->reorder_point : '',
                'preferred_supplier_id' => $v->preferred_supplier_id,
                'alternate_supplier_ids' => $v->alternate_supplier_ids ?? [],
                'attributes' => $v->attributes ?? [],
                'is_default' => $v->is_default,
            ])->values()->all(),
            'bundle_items' => $product->variants
                ->firstWhere('is_default', true)
                ?->bundleItems
                ->map(fn ($item) => [
                    'child_variant_id' => $item->child_variant_id,
                    'quantity' => (string) $item->quantity,
                    'child' => [
                        'id' => $item->childVariant?->id,
                        'sku' => $item->childVariant?->sku,
                        'name' => $item->childVariant?->displayName(),
                        'product_name' => $item->childVariant?->product?->name,
                    ],
                ])
                ->values()
                ->all() ?? [],
            'branch_prices' => $product->variants
                ->firstWhere('is_default', true)
                ?->branchPrices
                ->map(fn ($price) => [
                    'branch_id' => $price->branch_id,
                    'branch_name' => $price->branch?->name,
                    'sell_price' => (string) $price->sell_price,
                ])
                ->values()
                ->all() ?? [],
            'images' => ImagePresenter::collection($product->images),
        ];
    }
}
