<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Settings\SettingGroupRegistry;

final class SettingsPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->can('settings.view')) {
            return true;
        }

        foreach (SettingGroupRegistry::keys() as $group) {
            if ($user->can(SettingGroupRegistry::permission($group))) {
                return true;
            }
        }

        return false;
    }

    public function viewGroup(User $user, string $group): bool
    {
        if (! SettingGroupRegistry::exists($group)) {
            return false;
        }

        return $user->can('settings.view')
            || $user->can(SettingGroupRegistry::permission($group));
    }

    public function updateGroup(User $user, string $group): bool
    {
        if (! SettingGroupRegistry::exists($group)) {
            return false;
        }

        return $user->can(SettingGroupRegistry::permission($group));
    }
}
