<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class LeaveRequestPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.view')
            && $this->canAccessRequestBranch($user, $leaveRequest);
    }

    public function create(User $user): bool
    {
        return $user->can('leave.request');
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.approve')
            && $this->canAccessRequestBranch($user, $leaveRequest);
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.approve')
            && $this->canAccessRequestBranch($user, $leaveRequest);
    }

    public function reschedule(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.approve')
            && $this->canAccessRequestBranch($user, $leaveRequest);
    }

    private function canAccessRequestBranch(User $user, LeaveRequest $leaveRequest): bool
    {
        $employee = $leaveRequest->relationLoaded('employee') ? $leaveRequest->employee : $leaveRequest->employee()->first();

        if ($employee === null) {
            return true;
        }

        return BranchScope::canAccess(
            (int) $employee->primary_branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
