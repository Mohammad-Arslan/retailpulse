<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BranchAccountingProfile;
use App\Models\User;

final class BranchAccountingProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.manage-modules');
    }

    public function update(User $user, BranchAccountingProfile $branchAccountingProfile): bool
    {
        return $user->can('accounting.manage-modules');
    }
}
