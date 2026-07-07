<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

final class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view') || $user->can('customers.view-credit');
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.view') || $user->can('customers.write-off-debt');
    }
}
