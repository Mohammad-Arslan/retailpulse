<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FixedAsset;
use App\Models\User;

final class FixedAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.manage-assets');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-assets');
    }

    public function update(User $user, FixedAsset $fixedAsset): bool
    {
        return $user->can('accounting.manage-assets');
    }
}
