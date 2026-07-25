<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalaryStructure;
use App\Models\User;

final class SalaryStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.manage-structures') || $user->can('payroll.view');
    }

    public function view(User $user, SalaryStructure $salaryStructure): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.manage-structures');
    }

    public function update(User $user, SalaryStructure $salaryStructure): bool
    {
        return $user->can('payroll.manage-structures');
    }
}
