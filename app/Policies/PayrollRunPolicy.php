<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;

final class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view') || $user->can('payroll.process');
    }

    public function view(User $user, PayrollRun $payrollRun): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.process');
    }

    public function process(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('payroll.process');
    }

    public function approve(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('payroll.approve');
    }

    public function post(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('payroll.post');
    }

    public function reverse(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('payroll.reverse');
    }
}
