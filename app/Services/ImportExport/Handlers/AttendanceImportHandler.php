<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bulk historical attendance import — for onboarding past attendance data only.
 * Writes directly to attendance_records (flagged is_historical) without going
 * through the live AttendanceService/provider pipeline, mirroring how historical
 * sales import bypasses inventory/FBR side effects.
 */
final class AttendanceImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            ['key' => 'employee_code', 'label' => 'Employee Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim']],
            ['key' => 'branch_code', 'label' => 'Branch Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'clock_in', 'label' => 'Clock In', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'date']], 'default_transforms' => ['trim']],
            ['key' => 'clock_out', 'label' => 'Clock Out', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'worked_minutes', 'label' => 'Worked Minutes', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'integer'], ['rule' => 'min', 'value' => 0]], 'default_transforms' => ['trim', 'nullify_empty']],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $errors = [];

        $employeeCode = (string) ($row['employee_code'] ?? '');
        if ($employeeCode !== '' && ! Employee::query()->where('employee_code', $employeeCode)->exists()) {
            $errors['employee_code'] = [__('No employee found with code :code.', ['code' => $employeeCode])];
        }

        $clockIn = $row['clock_in'] ?? null;
        $clockOut = $row['clock_out'] ?? null;
        if ($clockIn !== null && $clockOut !== null && strtotime((string) $clockOut) <= strtotime((string) $clockIn)) {
            $errors['clock_out'] = [__('Clock out must be after clock in.')];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row) {
            $employee = Employee::query()->where('employee_code', (string) $row['employee_code'])->first();

            if ($employee === null) {
                return ImportRowResult::failure(__('No employee found with code :code.', ['code' => (string) $row['employee_code']]));
            }

            $branchId = $employee->primary_branch_id;
            if (! empty($row['branch_code'])) {
                $branchId = Branch::query()->where('code', (string) $row['branch_code'])->value('id') ?? $branchId;
            }

            $source = AttendanceSource::query()->firstOrCreate(
                ['driver' => 'historical_import'],
                ['name' => 'Historical Import', 'status' => 'active', 'branch_id' => null],
            );

            $clockIn = CarbonImmutable::parse((string) $row['clock_in']);
            $clockOut = ! empty($row['clock_out']) ? CarbonImmutable::parse((string) $row['clock_out']) : null;

            $workedMinutes = $row['worked_minutes'] ?? null;
            if ($workedMinutes === null && $clockOut !== null) {
                $workedMinutes = (int) $clockIn->diffInMinutes($clockOut);
            }

            $record = AttendanceRecord::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'source_id' => $source->id,
                    'clock_in' => $clockIn->toDateTimeString(),
                ],
                [
                    'branch_id' => $branchId,
                    'clock_out' => $clockOut?->toDateTimeString(),
                    'worked_minutes' => $workedMinutes,
                    'status' => $clockOut !== null ? 'closed' : 'open',
                    'is_historical' => true,
                ],
            );

            return ImportRowResult::success($record->id);
        });
    }

    public function afterImport(ImportContext $context): void {}

    public function chunkSize(): int
    {
        return 500;
    }
}
