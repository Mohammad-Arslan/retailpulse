<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;

final class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view') || $user->can('accounting.manage-fiscal-years');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-fiscal-years');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->can('accounting.manage-fiscal-years');
    }
}
