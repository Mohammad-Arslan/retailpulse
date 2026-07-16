<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\ApprovalDelegation;
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

    public function resolveManager(Employee $employee, ?CarbonInterface $date = null, string $scope = 'all'): ?Employee
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

        $managerId = null;

        if ($history !== null) {
            $managerId = $history->manager_employee_id;
        } elseif ($employee->reporting_manager_employee_id !== null) {
            $managerId = $employee->reporting_manager_employee_id;
        }

        if ($managerId === null) {
            return null;
        }

        $manager = Employee::query()->find($managerId);

        if ($manager === null) {
            return null;
        }

        $delegate = $this->resolveActiveDelegation($manager, $scope, $asOf);

        return $delegate ?? $manager;
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

    /**
     * @return list<array<string, mixed>>
     */
    public function orgChart(?int $legalEntityId = null, ?int $rootEmployeeId = null): array
    {
        $employees = Employee::query()
            ->where('status', 'active')
            ->when($legalEntityId !== null, fn ($q) => $q->where('legal_entity_id', $legalEntityId))
            ->with([
                'department:id,name',
                'designation:id,name',
            ])
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'first_name', 'last_name', 'reporting_manager_employee_id', 'department_id', 'designation_id']);

        $byId = $employees->keyBy('id');
        /** @var array<int, list<int>> $childrenMap */
        $childrenMap = [];

        foreach ($employees as $employee) {
            $managerId = $employee->reporting_manager_employee_id;
            if ($managerId !== null && $byId->has($managerId)) {
                $childrenMap[$managerId][] = $employee->id;
            }
        }

        $rootIds = $employees
            ->filter(function (Employee $employee) use ($byId): bool {
                $managerId = $employee->reporting_manager_employee_id;

                return $managerId === null || ! $byId->has($managerId);
            })
            ->pluck('id')
            ->all();

        if ($rootEmployeeId !== null) {
            if (! $byId->has($rootEmployeeId)) {
                return [];
            }
            $rootIds = [$rootEmployeeId];
        }

        $buildNode = function (int $employeeId) use (&$buildNode, $byId, $childrenMap): array {
            /** @var Employee $employee */
            $employee = $byId->get($employeeId);
            $childIds = $childrenMap[$employeeId] ?? [];

            return [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'children' => array_map(fn (int $id) => $buildNode($id), $childIds),
            ];
        };

        return array_map(fn (int $id) => $buildNode($id), $rootIds);
    }

    private function resolveActiveDelegation(
        Employee $fromEmployee,
        string $scope,
        CarbonInterface $date,
    ): ?Employee {
        $delegation = ApprovalDelegation::query()
            ->where('from_employee_id', $fromEmployee->id)
            ->where('status', 'active')
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date->toDateString());
            })
            ->where(function ($q) use ($scope): void {
                $q->where('scope', 'all')->orWhere('scope', $scope);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($delegation === null) {
            return null;
        }

        return Employee::query()->find($delegation->to_employee_id);
    }
}
