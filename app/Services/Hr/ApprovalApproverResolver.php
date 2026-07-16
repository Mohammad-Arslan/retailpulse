<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Department;
use App\Models\Employee;
use Carbon\CarbonInterface;

final class ApprovalApproverResolver
{
    public function __construct(
        private readonly ReportingHierarchyService $hierarchy,
    ) {}

    public function resolve(
        string $strategy,
        Employee $employee,
        ?CarbonInterface $date = null,
        string $scope = 'leave',
    ): ?Employee {
        return match ($strategy) {
            'direct_manager' => $this->hierarchy->resolveManager($employee, $date, $scope),
            'department_head' => $this->resolveDepartmentHead($employee),
            'workflow' => null,
            default => $this->hierarchy->resolveManager($employee, $date, $scope),
        };
    }

    public function resolveApproverUserId(
        string $strategy,
        Employee $employee,
        ?CarbonInterface $date = null,
        string $scope = 'leave',
    ): ?int {
        $approver = $this->resolve($strategy, $employee, $date, $scope);

        return $approver?->user_id;
    }

    /**
     * Resolves the configured head of the employee's own department, falling back up the
     * parent chain only when a department has no head assigned.
     */
    private function resolveDepartmentHead(Employee $employee): ?Employee
    {
        if ($employee->department_id === null) {
            return null;
        }

        $department = Department::query()->find($employee->department_id);

        while ($department !== null) {
            if ($department->head_employee_id !== null) {
                $head = Employee::query()
                    ->where('id', $department->head_employee_id)
                    ->where('status', 'active')
                    ->first();

                if ($head !== null) {
                    return $head;
                }
            }

            $department = $department->parent_id !== null
                ? Department::query()->find($department->parent_id)
                : null;
        }

        return null;
    }
}
