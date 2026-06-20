<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CountScheduleRule;
use App\Models\User;

final class CountScheduleRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.cycle-count');
    }

    public function view(User $user, CountScheduleRule $rule): bool
    {
        return $user->can('inventory.cycle-count');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.cycle-count');
    }

    public function update(User $user, CountScheduleRule $rule): bool
    {
        return $user->can('inventory.cycle-count');
    }

    public function delete(User $user, CountScheduleRule $rule): bool
    {
        return $user->can('inventory.cycle-count');
    }
}
