<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\HrEmploymentType;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class EmploymentTypeExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new EmploymentTypeImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        $query = HrEmploymentType::query()
            ->with(['legalEntity:id,legal_name'])
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
        /** @var HrEmploymentType $record */
        return [
            'code' => $record->code,
            'name' => $record->name,
            'legal_entity' => $record->legalEntity?->legal_name ?? '',
            'status' => $record->status,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
