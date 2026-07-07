<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

final class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.manage-bank-accounts')
            || $user->can('accounting.reconcile-bank');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-bank-accounts');
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('accounting.manage-bank-accounts');
    }
}
