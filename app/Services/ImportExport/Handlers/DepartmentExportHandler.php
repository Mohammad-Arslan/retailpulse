<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Department;
use App\Services\Hr\DepartmentService;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class DepartmentExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new DepartmentImportHandler(app(DepartmentService::class)))->columns();
    }

    public function query(ExportContext $context): Builder
    {
        $query = Department::query()
            ->with(['legalEntity:id,legal_name', 'parent:id,code', 'costCentre:id,code'])
            ->orderBy('code');

        $filters = $context->options['filters'] ?? [];
        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        return $query;
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Department $record */
        return [
            'code' => $record->code,
            'name' => $record->name,
            'legal_entity' => $record->legalEntity?->legal_name ?? '',
            'parent_code' => $record->parent?->code ?? '',
            'cost_centre_code' => $record->costCentre?->code ?? '',
            'status' => $record->status,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
