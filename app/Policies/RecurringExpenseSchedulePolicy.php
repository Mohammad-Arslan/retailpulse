<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RecurringExpenseSchedule;
use App\Models\User;

final class RecurringExpenseSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.manage-recurring') || $user->can('expenses.view');
    }

    public function view(User $user, RecurringExpenseSchedule $schedule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.manage-recurring');
    }
}
