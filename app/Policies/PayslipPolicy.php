<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

final class PayslipPolicy
{
    public function generate(User $user): bool
    {
        return $user->can('payroll.view') || $user->can('payroll.process');
    }

    public function bulkEmail(User $user): bool
    {
        return $user->can('payroll.process');
    }

    public function viewOwn(User $user): bool
    {
        return $user->can('selfservice.view-own');
    }

    public function downloadOwn(User $user, Payslip $payslip): bool
    {
        if (! $user->can('selfservice.view-own')) {
            return false;
        }

        $payslip->loadMissing('payrollItem.employee');

        return $payslip->payrollItem?->employee?->user_id === $user->id;
    }
}
