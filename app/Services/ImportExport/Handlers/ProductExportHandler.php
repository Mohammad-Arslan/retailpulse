<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class ProductExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new ProductImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        $query = ProductVariant::query()
            ->whereHas('product', fn ($q) => $q->where('tenant_id', $context->tenantId))
            ->with(['product.category', 'product.brand', 'product.unit'])
            ->orderBy('product_id')
            ->orderBy('sort_order');

        $productFilters = $context->options['filters'] ?? [];

        if (! empty($productFilters['category_id'])) {
            $query->whereHas(
                'product',
                fn ($q) => $q->where('category_id', (int) $productFilters['category_id']),
            );
        }

        if (! empty($productFilters['brand_id'])) {
            $query->whereHas(
                'product',
                fn ($q) => $q->where('brand_id', (int) $productFilters['brand_id']),
            );
        }

        if (! empty($productFilters['type'])) {
            $query->whereHas(
                'product',
                fn ($q) => $q->where('type', (string) $productFilters['type']),
            );
        }

        if (! empty($productFilters['search'])) {
            $search = (string) $productFilters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var ProductVariant $record */
        $product = $record->product;

        $row = [
            'name' => $product->name,
            'sku' => $record->sku,
            'category_code' => $product->category?->slug ?? '',
            'brand_code' => $product->brand?->slug ?? '',
            'unit_name' => $product->unit?->name ?? '',
            'barcode' => $record->barcode ?? '',
            'sell_price' => $record->sell_price,
            'type' => $product->type->value,
            'variant_label' => $record->name ?? '',
            'is_active' => $product->is_active ? 1 : 0,
        ];

        if ($this->canShowCost($context)) {
            $row['cost_price'] = $record->cost_price;
        }

        return $row;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function canShowCost(ExportContext $context): bool
    {
        return (bool) ($context->options['can_show_cost'] ?? false);
    }
}
