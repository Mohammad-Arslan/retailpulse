<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HolidayCalendar;
use App\Models\User;

final class HolidayCalendarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('holiday.manage') || $user->can('hr.view-employees');
    }

    public function view(User $user, HolidayCalendar $holidayCalendar): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('holiday.manage');
    }

    public function update(User $user, HolidayCalendar $holidayCalendar): bool
    {
        return $user->can('holiday.manage');
    }
}
