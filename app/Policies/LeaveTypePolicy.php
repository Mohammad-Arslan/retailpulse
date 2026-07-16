<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveType;
use App\Models\User;

final class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.manage-types') || $user->can('leave.view');
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $user->can('leave.manage-types') || $user->can('leave.view');
    }

    public function create(User $user): bool
    {
        return $user->can('leave.manage-types');
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $user->can('leave.manage-types');
    }
}
