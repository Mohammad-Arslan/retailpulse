<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaxType;
use App\Models\User;

final class TaxTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.manage-tax-settings') || $user->can('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-tax-settings');
    }

    public function update(User $user, TaxType $taxType): bool
    {
        return $user->can('accounting.manage-tax-settings');
    }
}
