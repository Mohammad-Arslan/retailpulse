<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceSource;
use App\Models\User;

final class AttendanceSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.manage-sources') || $user->can('attendance.view');
    }
}
