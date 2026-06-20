<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

final class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can('sales.view');
    }

    public function export(User $user): bool
    {
        return $user->can('sales.export');
    }

    public function importHistorical(User $user): bool
    {
        return $user->can('sales.import-historical');
    }
}
