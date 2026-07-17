<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\AttendanceRecord;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class AttendanceExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new AttendanceImportHandler)->columns();
    }

    public function query(ExportContext $context): Builder
    {
        $query = AttendanceRecord::query()
            ->with(['employee:id,employee_code', 'branch:id,code'])
            ->orderByDesc('clock_in');

        $filters = $context->options['filters'] ?? [];
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        return $query;
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var AttendanceRecord $record */
        return [
            'employee_code' => $record->employee?->employee_code ?? '',
            'branch_code' => $record->branch?->code ?? '',
            'clock_in' => $record->clock_in?->toDateTimeString() ?? '',
            'clock_out' => $record->clock_out?->toDateTimeString() ?? '',
            'worked_minutes' => (string) ($record->worked_minutes ?? ''),
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
