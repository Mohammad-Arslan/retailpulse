<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BranchHrProfile;
use App\Models\User;

final class BranchHrProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.manage-settings');
    }

    public function update(User $user, ?BranchHrProfile $branchHrProfile = null): bool
    {
        return $user->can('hr.manage-settings');
    }
}
