<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

final class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->can('products.delete');
    }
}
