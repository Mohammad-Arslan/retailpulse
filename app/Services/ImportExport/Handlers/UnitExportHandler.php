<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Unit;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class UnitExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new UnitImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return Unit::query()
            ->where('tenant_id', $context->tenantId)
            ->orderBy('name');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Unit $record */
        return [
            'name' => $record->name,
            'abbreviation' => $record->abbreviation ?? '',
            'is_active' => $record->is_active ? 1 : 0,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
