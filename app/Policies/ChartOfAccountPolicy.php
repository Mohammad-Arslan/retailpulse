<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ChartOfAccount;
use App\Models\User;

final class ChartOfAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view') || $user->can('accounting.manage-coa');
    }

    public function view(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-coa');
    }

    public function update(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->can('accounting.manage-coa');
    }
}
