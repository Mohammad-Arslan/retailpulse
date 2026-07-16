<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OvertimePolicy;
use App\Models\User;

final class OvertimePolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('overtime.manage-policies') || $user->can('overtime.view');
    }

    public function view(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('overtime.manage-policies');
    }

    public function update(User $user, OvertimePolicy $overtimePolicy): bool
    {
        return $user->can('overtime.manage-policies');
    }
}
