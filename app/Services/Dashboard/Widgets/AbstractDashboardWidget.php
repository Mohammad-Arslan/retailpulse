<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;
use App\Services\Dashboard\Contracts\DashboardWidget;

abstract class AbstractDashboardWidget implements DashboardWidget
{
    public function isVisible(User $user): bool
    {
        foreach ($this->permissions() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function userCanAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
