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
     * Resolves the head of the employee's department tree (root department's first active employee).
     * Limitation: does not distinguish formal "head" role — uses first active employee in root department.
     */
    private function resolveDepartmentHead(Employee $employee): ?Employee
    {
        if ($employee->department_id === null) {
            return null;
        }

        $department = Department::query()->find($employee->department_id);
        if ($department === null) {
            return null;
        }

        while ($department->parent_id !== null) {
            $parent = Department::query()->find($department->parent_id);
            if ($parent === null) {
                break;
            }
            $department = $parent;
        }

        return Employee::query()
            ->where('department_id', $department->id)
            ->where('status', 'active')
            ->orderBy('employee_code')
            ->first();
    }
}
