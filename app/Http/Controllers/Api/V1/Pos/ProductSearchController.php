<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Models\BranchProductPrice;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Support\Pos\PosBranchWarehouses;
use App\Support\Pos\PosSellableProducts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $search = trim((string) $request->input('q', ''));
        $branchId = (int) $request->input('branch_id');

        if ($search === '') {
            return response()->json(['results' => []]);
        }

        $variants = ProductVariant::query()
            ->select([
                'product_variants.id',
                'product_variants.product_id',
                'product_variants.sku',
                'product_variants.barcode',
                'product_variants.name as variant_name',
                'product_variants.sell_price',
            ])
            ->with(['product:id,name,is_active,type'])
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.is_active', true)
            ->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('product_variants.sku', 'like', "%{$search}%")
                    ->orWhere('product_variants.barcode', 'like', "%{$search}%");
            });

        PosSellableProducts::restrictToInStock($variants, $branchId);

        $variants = $variants
            ->limit(20)
            ->get();

        $branchPrices = BranchProductPrice::query()
            ->where('branch_id', $branchId)
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->pluck('sell_price', 'product_variant_id');

        $warehouseIds = PosBranchWarehouses::activeIds($branchId);
        $stockLevels = [];

        if ($warehouseIds !== []) {
            $stockLevels = Inventory::query()
                ->whereIn('warehouse_id', $warehouseIds)
                ->whereIn('product_variant_id', $variants->pluck('id'))
                ->selectRaw('product_variant_id, SUM(quantity_on_hand - quantity_reserved - COALESCE(quantity_in_quarantine, 0)) as available')
                ->groupBy('product_variant_id')
                ->pluck('available', 'product_variant_id')
                ->map(fn ($v) => max(0, (int) $v))
                ->all();
        }

        $results = $variants->map(function (ProductVariant $variant) use ($branchPrices, $stockLevels) {
            $price = $branchPrices[$variant->id] ?? $variant->sell_price ?? 0;
            $available = $stockLevels[$variant->id] ?? 0;
            $tracksInventory = $variant->product->tracksInventory();

            return [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'name' => $variant->product->name.($variant->variant_name ? ' — '.$variant->variant_name : ''),
                'unit_price' => (float) $price,
                'product_type' => $variant->product?->type?->value,
                'tracks_inventory' => $tracksInventory,
                'available_stock' => $tracksInventory ? $available : null,
                'in_stock' => ! $tracksInventory || $available > 0,
            ];
        });

        return response()->json(['results' => $results->all()]);
    }
}
