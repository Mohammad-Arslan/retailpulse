<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveEntitlement;
use App\Models\User;

final class LeaveEntitlementPolicy
{
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
        return $user->can('leave.manage-entitlements');
    }
}
