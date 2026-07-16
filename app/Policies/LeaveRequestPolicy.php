<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

final class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.view');
    }

    public function create(User $user): bool
    {
        return $user->can('leave.request');
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.approve');
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.approve');
    }
}
