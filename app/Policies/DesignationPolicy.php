<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Designation;
use App\Models\User;

final class DesignationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.view-employees') || $user->can('hr.manage-org');
    }

    public function view(User $user, Designation $designation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.manage-org');
    }

    public function update(User $user, Designation $designation): bool
    {
        return $user->can('hr.manage-org');
    }
}
