<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaxSlab;
use App\Models\User;

final class TaxSlabPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.manage-tax-slabs') || $user->can('payroll.view');
    }

    public function view(User $user, TaxSlab $taxSlab): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.manage-tax-slabs');
    }

    public function update(User $user, TaxSlab $taxSlab): bool
    {
        return $user->can('payroll.manage-tax-slabs');
    }

    public function delete(User $user, TaxSlab $taxSlab): bool
    {
        return $user->can('payroll.manage-tax-slabs');
    }
}
