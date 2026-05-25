<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Models\BranchProductPrice;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductCatalogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $branchId = (int) $request->input('branch_id');
        $categoryId = $request->input('category_id');
        $search = trim((string) $request->input('q', ''));
        $perPage = (int) $request->input('per_page', 24);

        $warehouseId = $this->defaultWarehouseId($branchId);

        $query = ProductVariant::query()
            ->select([
                'product_variants.id',
                'product_variants.product_id',
                'product_variants.sku',
                'product_variants.barcode',
                'product_variants.name as variant_name',
                'product_variants.sell_price',
            ])
            ->with(['product.primaryImage', 'product:id,name,category_id,is_active'])
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.is_active', true)
            ->when($categoryId, fn ($q) => $q->where('products.category_id', (int) $categoryId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('products.name', 'like', "%{$search}%")
                        ->orWhere('product_variants.sku', 'like', "%{$search}%")
                        ->orWhere('product_variants.barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('products.name')
            ->orderBy('product_variants.sort_order');

        $paginator = $query->paginate($perPage);

        $variantIds = collect($paginator->items())->pluck('id');

        $branchPrices = BranchProductPrice::query()
            ->where('branch_id', $branchId)
            ->whereIn('product_variant_id', $variantIds)
            ->pluck('sell_price', 'product_variant_id');

        $stockLevels = [];

        if ($warehouseId !== null && $variantIds->isNotEmpty()) {
            $stockLevels = Inventory::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('product_variant_id', $variantIds)
                ->selectRaw('product_variant_id, SUM(quantity_on_hand - quantity_reserved) as available')
                ->groupBy('product_variant_id')
                ->pluck('available', 'product_variant_id')
                ->map(fn ($v) => max(0, (int) $v))
                ->all();
        }

        $results = collect($paginator->items())->map(function (ProductVariant $variant) use ($branchPrices, $stockLevels) {
            $price = $branchPrices[$variant->id] ?? $variant->sell_price ?? 0;
            $available = $stockLevels[$variant->id] ?? 0;
            $image = $variant->product?->primaryImage;

            return [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'category_id' => $variant->product?->category_id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'name' => $variant->product->name.($variant->variant_name ? ' — '.$variant->variant_name : ''),
                'unit_price' => (float) $price,
                'available_stock' => $available,
                'in_stock' => $available > 0,
                'image_url' => $image !== null ? ($image->thumbnailUrl() ?? $image->url()) : null,
            ];
        });

        return response()->json([
            'results' => $results->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function defaultWarehouseId(int $branchId): ?int
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
}
