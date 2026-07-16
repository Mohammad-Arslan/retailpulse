<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApprovalDelegation;
use App\Models\User;

final class ApprovalDelegationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.manage-org');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.manage-org');
    }

    public function update(User $user, ApprovalDelegation $delegation): bool
    {
        return $user->can('hr.manage-org');
    }
}
