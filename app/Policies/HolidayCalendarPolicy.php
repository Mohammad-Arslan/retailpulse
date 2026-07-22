<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HolidayCalendar;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class HolidayCalendarPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('holiday.manage') || $user->can('hr.view-employees');
    }

    public function view(User $user, HolidayCalendar $holidayCalendar): bool
    {
        return $this->viewAny($user) && $this->canAccessCalendarBranch($user, $holidayCalendar);
    }

    public function create(User $user): bool
    {
        return $user->can('holiday.manage');
    }

    public function update(User $user, HolidayCalendar $holidayCalendar): bool
    {
        return $user->can('holiday.manage')
            && $this->canAccessCalendarBranch($user, $holidayCalendar);
    }

    private function canAccessCalendarBranch(User $user, HolidayCalendar $holidayCalendar): bool
    {
        if ($holidayCalendar->branch_id === null) {
            return true;
        }

        return BranchScope::canAccess(
            (int) $holidayCalendar->branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
