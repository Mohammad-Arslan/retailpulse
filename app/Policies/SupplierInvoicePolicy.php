<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierInvoice;
use App\Models\User;

final class SupplierInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, SupplierInvoice $invoice): bool
    {
        return $user->can('procurement.view');
    }

    public function create(User $user): bool
    {
        return $user->can('procurement.create');
    }

    public function approve(User $user, SupplierInvoice $invoice): bool
    {
        return $user->can('procurement.create');
    }
}
