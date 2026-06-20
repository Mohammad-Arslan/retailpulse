<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\ProductVariant;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use App\Support\CatalogExportFilters;
use App\Support\TenantImportScope;
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
            ->whereHas('product', fn ($q) => TenantImportScope::constrain($q, $context->tenantId))
            ->with(['product.category', 'product.brand', 'product.unit', 'preferredSupplier'])
            ->orderBy('product_id')
            ->orderBy('sort_order');

        CatalogExportFilters::applyProductVariantFilters(
            $query,
            $context->options['filters'] ?? [],
        );

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
            'category_slug' => $product->category?->slug ?? '',
            'brand_code' => $product->brand?->slug ?? '',
            'brand_slug' => $product->brand?->slug ?? '',
            'unit_name' => $product->unit?->name ?? '',
            'barcode' => $record->barcode ?? '',
            'sell_price' => $record->sell_price,
            'type' => $product->type->value,
            'variant_label' => $record->name ?? '',
            'is_active' => $product->is_active ? 1 : 0,
            'preferred_supplier_code' => $record->preferredSupplier?->code ?? '',
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
