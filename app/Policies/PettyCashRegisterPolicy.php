<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class PettyCashRegisterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.manage-petty-cash');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-petty-cash');
    }
}
