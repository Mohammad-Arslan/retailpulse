<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Product;
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
        $query = Product::query()
            ->where('tenant_id', $context->tenantId)
            ->with(['category', 'variants' => fn ($q) => $q->orderBy('sort_order')]);

        if (isset($context->options['category_id'])) {
            $query->where('category_id', (int) $context->options['category_id']);
        }

        return $query;
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Product $record */
        $variant = $record->variants->first();

        return [
            'name' => $record->name,
            'sku' => $variant?->sku ?? '',
            'category_code' => $record->category?->slug ?? '',
            'barcode' => $variant?->barcode ?? '',
            'cost_price' => $variant?->cost_price ?? 0,
            'sell_price' => $variant?->sell_price ?? 0,
            'type' => $record->type->value,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
