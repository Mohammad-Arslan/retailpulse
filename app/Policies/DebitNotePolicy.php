<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DebitNote;
use App\Models\User;

final class DebitNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, DebitNote $debitNote): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('procurement.manage-returns');
    }
}
