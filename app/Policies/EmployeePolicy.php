<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class EmployeePolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('hr.view-employees') || $user->can('hr.manage-employees');
    }

    public function view(User $user, Employee $employee): bool
    {
        return ($user->can('hr.view-employees') || $user->can('hr.manage-employees'))
            && $this->canAccessEmployeeBranch($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.manage-employees');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hr.manage-employees')
            && $this->canAccessEmployeeBranch($user, $employee);
    }

    public function terminate(User $user, Employee $employee): bool
    {
        return $user->can('hr.manage-employees')
            && $this->canAccessEmployeeBranch($user, $employee);
    }

    public function reactivate(User $user, Employee $employee): bool
    {
        return $user->can('hr.manage-employees')
            && $this->canAccessEmployeeBranch($user, $employee);
    }

    private function canAccessEmployeeBranch(User $user, Employee $employee): bool
    {
        return BranchScope::canAccess(
            (int) $employee->primary_branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
