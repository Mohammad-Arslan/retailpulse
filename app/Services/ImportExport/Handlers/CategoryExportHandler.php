<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Category;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class CategoryExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new CategoryImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return Category::query()
            ->where('tenant_id', $context->tenantId)
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Category $record */
        return [
            'code' => $record->slug,
            'name' => $record->name,
            'parent_code' => $record->parent?->slug ?? '',
            'description' => $record->description ?? '',
            'sort_order' => $record->sort_order,
            'is_active' => $record->is_active ? 1 : 0,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
