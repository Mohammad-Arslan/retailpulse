<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

final class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->can('products.delete');
    }
}
