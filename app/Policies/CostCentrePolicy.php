<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CostCentre;
use App\Models\User;

final class CostCentrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.manage-cost-centres') || $user->can('accounting.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-cost-centres');
    }

    public function update(User $user, CostCentre $costCentre): bool
    {
        return $user->can('accounting.manage-cost-centres');
    }

    public function delete(User $user, CostCentre $costCentre): bool
    {
        return $user->can('accounting.manage-cost-centres');
    }
}
