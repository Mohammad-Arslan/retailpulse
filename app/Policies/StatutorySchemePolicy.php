<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StatutoryScheme;
use App\Models\User;

final class StatutorySchemePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.manage-statutory') || $user->can('payroll.view');
    }

    public function view(User $user, StatutoryScheme $statutoryScheme): bool
    {
        return $this->viewAny($user);
    }
}
