<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ToilClaim;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class ToilClaimPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('overtime.view') || $user->can('leave.view');
    }

    public function view(User $user, ToilClaim $toilClaim): bool
    {
        return ($user->can('overtime.view') || $user->can('leave.view'))
            && $this->canAccessClaimBranch($user, $toilClaim);
    }

    public function create(User $user): bool
    {
        return $user->can('toil.request-cash-claim');
    }

    public function approve(User $user, ToilClaim $toilClaim): bool
    {
        return $user->can('toil.approve-cash-claim')
            && $this->canAccessClaimBranch($user, $toilClaim);
    }

    public function reject(User $user, ToilClaim $toilClaim): bool
    {
        return $user->can('toil.approve-cash-claim')
            && $this->canAccessClaimBranch($user, $toilClaim);
    }

    public function cancel(User $user, ToilClaim $toilClaim): bool
    {
        return ($user->can('toil.approve-cash-claim') || $user->can('toil.request-cash-claim'))
            && $this->canAccessClaimBranch($user, $toilClaim);
    }

    private function canAccessClaimBranch(User $user, ToilClaim $toilClaim): bool
    {
        $employee = $toilClaim->relationLoaded('employee') ? $toilClaim->employee : $toilClaim->employee()->first();

        if ($employee === null) {
            return true;
        }

        return BranchScope::canAccess(
            (int) $employee->primary_branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
