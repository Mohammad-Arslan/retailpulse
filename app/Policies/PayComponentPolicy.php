<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PayComponent;
use App\Models\User;

final class PayComponentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.manage-components') || $user->can('payroll.view');
    }

    public function view(User $user, PayComponent $payComponent): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.manage-components');
    }

    public function update(User $user, PayComponent $payComponent): bool
    {
        return $user->can('payroll.manage-components');
    }

    public function delete(User $user, PayComponent $payComponent): bool
    {
        return $user->can('payroll.manage-components');
    }
}
