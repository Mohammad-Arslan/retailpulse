<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cheque;
use App\Models\User;

final class ChequePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.manage-cheques');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-cheques');
    }

    public function update(User $user, Cheque $cheque): bool
    {
        return $user->can('accounting.manage-cheques');
    }
}
