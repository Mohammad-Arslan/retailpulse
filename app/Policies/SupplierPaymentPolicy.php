<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;

final class SupplierPaymentPolicy
{
    public function create(User $user): bool
    {
        return $user->can('procurement.process-payments');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('procurement.process-payments');
    }

    public function view(User $user, SupplierPayment $payment): bool
    {
        return $user->can('procurement.process-payments');
    }
}
