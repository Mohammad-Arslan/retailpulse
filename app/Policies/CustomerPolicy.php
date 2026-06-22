<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete');
    }

    public function viewCredit(User $user, Customer $customer): bool
    {
        return $user->can('customers.view-credit');
    }

    public function import(User $user): bool
    {
        return $user->can('customers.import');
    }

    public function export(User $user): bool
    {
        return $user->can('customers.export');
    }

    public function writeOffDebt(User $user, Customer $customer): bool
    {
        return $user->can('customers.write-off-debt');
    }
}
