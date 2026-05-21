<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Brand;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use App\Support\CatalogExportFilters;
use App\Support\TenantImportScope;
use Illuminate\Database\Eloquent\Builder;

final class BrandExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new BrandImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        $query = TenantImportScope::constrain(Brand::query(), $context->tenantId)
            ->orderBy('name');

        CatalogExportFilters::applyBrandFilters(
            $query,
            $context->options['filters'] ?? [],
        );

        return $query;
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Brand $record */
        return [
            'code' => $record->slug,
            'name' => $record->name,
            'description' => $record->description ?? '',
            'is_active' => $record->is_active ? 1 : 0,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
