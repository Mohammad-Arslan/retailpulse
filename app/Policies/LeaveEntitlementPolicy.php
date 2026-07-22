<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveEntitlement;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class LeaveEntitlementPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('leave.manage-entitlements') || $user->can('leave.view');
    }

    public function create(User $user): bool
    {
        return $user->can('leave.manage-entitlements');
    }

    public function update(User $user, LeaveEntitlement $leaveEntitlement): bool
    {
        if (! $user->can('leave.manage-entitlements')) {
            return false;
        }

        $employee = $leaveEntitlement->relationLoaded('employee')
            ? $leaveEntitlement->employee
            : $leaveEntitlement->employee()->first();

        if ($employee === null) {
            return true;
        }

        return BranchScope::canAccess(
            (int) $employee->primary_branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
