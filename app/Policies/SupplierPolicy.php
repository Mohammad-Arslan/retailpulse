<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

final class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view') || $user->can('procurement.manage-suppliers');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('procurement.manage-suppliers');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('procurement.manage-suppliers');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('procurement.manage-suppliers');
    }

    public function processPayments(User $user, Supplier $supplier): bool
    {
        return $user->can('procurement.process-payments');
    }

    public function deactivate(User $user, Supplier $supplier): bool
    {
        return $user->can('procurement.manage-suppliers');
    }
}
