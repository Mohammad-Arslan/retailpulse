<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Employee;
use App\Services\Hr\ReportingHierarchyService;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use Illuminate\Database\Eloquent\Builder;

final class ReportingHierarchyExportHandler implements ExportHandler
{
    public function columns(): array
    {
        return (new ReportingHierarchyImportHandler(app(ReportingHierarchyService::class)))->columns();
    }

    public function query(ExportContext $context): Builder
    {
        return Employee::query()
            ->with('reportingManager:id,employee_code')
            ->where('status', 'active')
            ->orderBy('employee_code');
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Employee $record */
        return [
            'employee_code' => $record->employee_code,
            'manager_employee_code' => $record->reportingManager?->employee_code ?? '',
            'effective_from' => '',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
