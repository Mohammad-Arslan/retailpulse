<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Brand;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class BrandExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new BrandImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return Brand::query()
            ->where('tenant_id', $context->tenantId)
            ->orderBy('name');
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
