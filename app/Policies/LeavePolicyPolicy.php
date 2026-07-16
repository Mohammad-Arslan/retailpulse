<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeavePolicy;
use App\Models\User;

final class LeavePolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.manage-policies') || $user->can('leave.view');
    }

    public function update(User $user, LeavePolicy $leavePolicy): bool
    {
        return $user->can('leave.manage-policies');
    }
}
