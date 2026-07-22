<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OvertimePolicy;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class OvertimePolicyPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('overtime.manage-policies') || $user->can('overtime.view');
    }

    public function view(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $this->viewAny($user) && $this->canAccessPolicyBranch($user, $overtimePolicy);
    }

    public function create(User $user): bool
    {
        return $user->can('overtime.manage-policies');
    }

    public function update(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $user->can('overtime.manage-policies')
            && $this->canAccessPolicyBranch($user, $overtimePolicy);
    }

    private function canAccessPolicyBranch(User $user, OvertimePolicy $overtimePolicy): bool
    {
        if ($overtimePolicy->branch_id === null) {
            return true;
        }

        return BranchScope::canAccess(
            (int) $overtimePolicy->branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
