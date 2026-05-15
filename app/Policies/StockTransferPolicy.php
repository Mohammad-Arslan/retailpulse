<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

final class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.transfer');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->can('inventory.transfer');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.transfer');
    }

    public function ship(User $user, StockTransfer $transfer): bool
    {
        return $user->can('inventory.transfer')
            && $transfer->status->canShip();
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return $user->can('inventory.transfer')
            && $transfer->status->canReceive();
    }
}
