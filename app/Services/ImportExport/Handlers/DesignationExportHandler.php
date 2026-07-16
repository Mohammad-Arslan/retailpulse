<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Designation;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class DesignationExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new DesignationImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return Designation::query()->with(['legalEntity:id,legal_name', 'defaultGrade:id,code'])->orderBy('code');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Designation $record */
        return [
            'code' => $record->code,
            'name' => $record->name,
            'legal_entity' => $record->legalEntity?->legal_name ?? '',
            'grade_code' => $record->defaultGrade?->code ?? '',
            'status' => $record->status,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
