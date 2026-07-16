<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

final class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.view-employees') || $user->can('hr.manage-employees');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('hr.view-employees') || $user->can('hr.manage-employees');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.manage-employees');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hr.manage-employees');
    }

    public function terminate(User $user, Employee $employee): bool
    {
        return $user->can('hr.manage-employees');
    }

    public function reactivate(User $user, Employee $employee): bool
    {
        return $user->can('hr.manage-employees');
    }
}
