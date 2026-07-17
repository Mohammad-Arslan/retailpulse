<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveYearEndRun;
use App\Models\User;

final class LeaveYearEndRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function view(User $user, LeaveYearEndRun $leaveYearEndRun): bool
    {
        return $user->can('leave.view');
    }
}
