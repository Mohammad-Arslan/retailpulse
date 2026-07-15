<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeManagerHistory;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class ReportingHierarchyService
{
    public function assertNoCycle(int $employeeId, int $managerId): void
    {
        if ($employeeId === $managerId) {
            throw ValidationException::withMessages([
                'reporting_manager_employee_id' => __('An employee cannot report to themselves.'),
            ]);
        }

        $currentId = $managerId;
        $visited = [];

        while ($currentId !== null) {
            if ($currentId === $employeeId) {
                throw ValidationException::withMessages([
                    'reporting_manager_employee_id' => __('This manager assignment would create a reporting cycle.'),
                ]);
            }

            if (isset($visited[$currentId])) {
                break;
            }

            $visited[$currentId] = true;
            $currentId = Employee::query()->whereKey($currentId)->value('reporting_manager_employee_id');
        }
    }

    public function resolveManager(Employee $employee, ?CarbonInterface $date = null): ?Employee
    {
        $asOf = $date ?? now();

        $history = EmployeeManagerHistory::query()
            ->where('employee_id', $employee->id)
            ->where('effective_from', '<=', $asOf->toDateString())
            ->where(function ($q) use ($asOf): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $asOf->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($history !== null && $history->manager_employee_id !== null) {
            return Employee::query()->find($history->manager_employee_id);
        }

        if ($employee->reporting_manager_employee_id === null) {
            return null;
        }

        return Employee::query()->find($employee->reporting_manager_employee_id);
    }

    public function recordManagerChange(Employee $employee, ?int $newManagerId, int $changedByUserId): void
    {
        $today = now()->toDateString();

        EmployeeManagerHistory::query()
            ->where('employee_id', $employee->id)
            ->whereNull('effective_to')
            ->update(['effective_to' => $today]);

        EmployeeManagerHistory::query()->create([
            'employee_id' => $employee->id,
            'manager_employee_id' => $newManagerId,
            'effective_from' => $today,
            'changed_by' => $changedByUserId,
        ]);
    }
}
