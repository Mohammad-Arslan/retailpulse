<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CountSession;
use App\Models\User;

final class CountSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.cycle-count');
    }

    public function view(User $user, CountSession $session): bool
    {
        return $user->can('inventory.cycle-count');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.cycle-count');
    }

    public function update(User $user, CountSession $session): bool
    {
        return $user->can('inventory.cycle-count');
    }

    public function approve(User $user, CountSession $session): bool
    {
        return $user->can('inventory.cycle-count.approve');
    }
}
