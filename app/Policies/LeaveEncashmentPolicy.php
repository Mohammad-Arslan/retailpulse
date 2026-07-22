<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveEncashment;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class LeaveEncashmentPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function view(User $user, LeaveEncashment $leaveEncashment): bool
    {
        return $user->can('leave.view')
            && $this->canAccessEncashmentBranch($user, $leaveEncashment);
    }

    public function create(User $user): bool
    {
        return $user->can('leave.request-encashment');
    }

    public function approve(User $user, LeaveEncashment $leaveEncashment): bool
    {
        return $user->can('leave.approve-encashment')
            && $this->canAccessEncashmentBranch($user, $leaveEncashment);
    }

    public function reject(User $user, LeaveEncashment $leaveEncashment): bool
    {
        return $user->can('leave.approve-encashment')
            && $this->canAccessEncashmentBranch($user, $leaveEncashment);
    }

    public function cancel(User $user, LeaveEncashment $leaveEncashment): bool
    {
        return ($user->can('leave.approve-encashment') || $user->can('leave.request-encashment'))
            && $this->canAccessEncashmentBranch($user, $leaveEncashment);
    }

    private function canAccessEncashmentBranch(User $user, LeaveEncashment $leaveEncashment): bool
    {
        $employee = $leaveEncashment->relationLoaded('employee')
            ? $leaveEncashment->employee
            : $leaveEncashment->employee()->first();

        if ($employee === null) {
            return true;
        }

        return BranchScope::canAccess(
            (int) $employee->primary_branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
