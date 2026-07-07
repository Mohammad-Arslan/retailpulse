<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccountMapping;
use App\Models\User;

final class AccountMappingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view') || $user->can('accounting.manage-mappings');
    }

    public function view(User $user, AccountMapping $accountMapping): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.manage-mappings');
    }

    public function update(User $user, AccountMapping $accountMapping): bool
    {
        return $user->can('accounting.manage-mappings');
    }

    public function delete(User $user, AccountMapping $accountMapping): bool
    {
        return $user->can('accounting.manage-mappings');
    }
}
