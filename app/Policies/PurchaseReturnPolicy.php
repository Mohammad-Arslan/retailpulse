<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;

final class PurchaseReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, PurchaseReturn $return): bool
    {
        return $user->can('procurement.view');
    }

    public function create(User $user): bool
    {
        return $user->can('procurement.manage-returns');
    }

    public function approve(User $user, PurchaseReturn $return): bool
    {
        return $user->can('procurement.manage-returns');
    }

    public function dispatch(User $user, PurchaseReturn $return): bool
    {
        return $user->can('procurement.manage-returns');
    }

    public function acknowledge(User $user, PurchaseReturn $return): bool
    {
        return $user->can('procurement.manage-returns');
    }

    public function issueDebitNote(User $user, PurchaseReturn $return): bool
    {
        return $user->can('procurement.manage-returns');
    }

    public function close(User $user, PurchaseReturn $return): bool
    {
        return $user->can('procurement.manage-returns');
    }
}
