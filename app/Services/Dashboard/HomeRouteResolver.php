<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\User;

/**
 * Permission-driven post-login / home target. Roles are never consulted.
 */
final class HomeRouteResolver
{
    public function routeName(?User $user): string
    {
        if ($user === null) {
            return 'login';
        }

        if ($user->can('dashboard.view') || $user->can('admin.dashboard.view')) {
            return 'admin.dashboard';
        }

        if ($user->can('pos.access')) {
            return 'admin.pos.index';
        }

        if ($user->can('admin.access') && $user->can('sales.view')) {
            return 'admin.sales.index';
        }

        if ($user->can('admin.access')) {
            return 'help-support.index';
        }

        return 'login';
    }

    public function url(?User $user): string
    {
        $name = $this->routeName($user);

        if ($name === 'login') {
            return route('login', absolute: false);
        }

        return route($name, absolute: false);
    }

    /**
     * Show Exit To ERP only when the user can enter the admin/ERP shell.
     * POS-only cashiers (pos.access without admin/dashboard permissions) must not see it.
     */
    public function canExitToErp(User $user): bool
    {
        return $user->can('dashboard.view')
            || $user->can('admin.dashboard.view')
            || $user->can('admin.access');
    }
}