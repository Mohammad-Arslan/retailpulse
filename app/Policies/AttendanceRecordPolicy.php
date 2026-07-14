<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

final class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.record');
    }

    public function adjust(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.adjust');
    }
}
