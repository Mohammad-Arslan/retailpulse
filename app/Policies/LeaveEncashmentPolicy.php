<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveEncashment;
use App\Models\User;

final class LeaveEncashmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function view(User $user, LeaveEncashment $leaveEncashment): bool
    {
        return $user->can('leave.view');
    }

    public function create(User $user): bool
    {
        return $user->can('leave.request-encashment');
    }

    public function approve(User $user, LeaveEncashment $leaveEncashment): bool
    {
        return $user->can('leave.approve-encashment');
    }

    public function reject(User $user, LeaveEncashment $leaveEncashment): bool
    {
        return $user->can('leave.approve-encashment');
    }

    public function cancel(User $user, LeaveEncashment $leaveEncashment): bool
    {
        return $user->can('leave.approve-encashment') || $user->can('leave.request-encashment');
    }
}
