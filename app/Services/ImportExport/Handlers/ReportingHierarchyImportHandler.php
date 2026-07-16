<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Employee;
use App\Services\Hr\ReportingHierarchyService;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ReportingHierarchyImportHandler implements ImportHandler
{
    public function __construct(
        private readonly ReportingHierarchyService $hierarchy,
    ) {}

    public function columns(): array
    {
        return [
            ['key' => 'employee_code', 'label' => 'Employee Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'uppercase']],
            ['key' => 'manager_employee_code', 'label' => 'Manager Employee Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'uppercase', 'nullify_empty']],
            ['key' => 'effective_from', 'label' => 'Effective From', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']], 'default_transforms' => ['trim', 'date_normalize', 'nullify_empty']],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $errors = [];
        $code = (string) ($row['employee_code'] ?? '');
        if ($code !== '' && ! Employee::query()->where('employee_code', $code)->exists()) {
            $errors['employee_code'] = ['Employee not found.'];
        }
        $managerCode = (string) ($row['manager_employee_code'] ?? '');
        if ($managerCode !== '' && ! Employee::query()->where('employee_code', $managerCode)->exists()) {
            $errors['manager_employee_code'] = ['Manager employee not found.'];
        }
        if ($managerCode !== '' && strtoupper($managerCode) === strtoupper($code)) {
            $errors['manager_employee_code'] = ['An employee cannot report to themselves.'];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row, $context) {
            $employee = Employee::query()->where('employee_code', (string) $row['employee_code'])->first();
            if ($employee === null) {
                return ImportRowResult::failure('Employee not found.');
            }

            $managerId = null;
            if (! empty($row['manager_employee_code'])) {
                $managerId = Employee::query()
                    ->where('employee_code', (string) $row['manager_employee_code'])
                    ->value('id');
                if ($managerId === null) {
                    return ImportRowResult::failure('Manager employee not found.');
                }
                $this->hierarchy->assertNoCycle($employee->id, (int) $managerId);
            }

            $employee->update(['reporting_manager_employee_id' => $managerId]);
            $this->hierarchy->recordManagerChange($employee, $managerId, $context->userId ?: (int) Auth::id());

            return ImportRowResult::success($employee->id);
        });
    }

    public function afterImport(ImportContext $context): void {}

    public function chunkSize(): int
    {
        return 200;
    }
}
