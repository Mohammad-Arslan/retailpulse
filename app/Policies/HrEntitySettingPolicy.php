<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class HrEntitySettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.manage-settings');
    }

    public function update(User $user): bool
    {
        return $user->can('hr.manage-settings');
    }
}
