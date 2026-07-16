<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Grade;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class GradeExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new GradeImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return Grade::query()->with('legalEntity:id,legal_name')->orderBy('rank')->orderBy('code');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Grade $record */
        return [
            'code' => $record->code,
            'name' => $record->name,
            'legal_entity' => $record->legalEntity?->legal_name ?? '',
            'rank' => $record->rank,
            'currency_code' => $record->currency_code ?? '',
            'min_amount' => $record->min_amount ?? '',
            'mid_amount' => $record->mid_amount ?? '',
            'max_amount' => $record->max_amount ?? '',
            'effective_from' => optional($record->effective_from)?->toDateString() ?? '',
            'effective_to' => optional($record->effective_to)?->toDateString() ?? '',
            'status' => $record->status,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
