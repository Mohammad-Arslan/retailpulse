<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

final class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.view-employees') || $user->can('hr.manage-org');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.manage-org');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('hr.manage-org');
    }
}
