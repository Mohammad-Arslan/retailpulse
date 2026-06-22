<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LandedCostEntry;
use App\Models\User;

final class LandedCostEntryPolicy
{
    public function create(User $user): bool
    {
        return $user->can('procurement.create') || $user->can('procurement.receive-grn');
    }

    public function delete(User $user, LandedCostEntry $entry): bool
    {
        return $user->can('procurement.create') || $user->can('procurement.receive-grn');
    }
}
