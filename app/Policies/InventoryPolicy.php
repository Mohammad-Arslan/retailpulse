<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.reports') || $user->can('inventory.view');
    }

    public function adjust(User $user): bool
    {
        return $user->can('inventory.adjust');
    }

    public function receive(User $user): bool
    {
        return $user->can('inventory.receive');
    }
}
