<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LoyaltyProgram;
use App\Models\User;

final class LoyaltyProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loyalty.view');
    }

    public function view(User $user, LoyaltyProgram $program): bool
    {
        return $user->can('loyalty.view');
    }

    public function create(User $user): bool
    {
        return $user->can('loyalty.manage-programs');
    }

    public function update(User $user, LoyaltyProgram $program): bool
    {
        return $user->can('loyalty.manage-programs');
    }

    public function delete(User $user, LoyaltyProgram $program): bool
    {
        return $user->can('loyalty.manage-programs');
    }

    public function manageRules(User $user): bool
    {
        return $user->can('loyalty.manage-rules');
    }

    public function adjustPoints(User $user): bool
    {
        return $user->can('loyalty.adjust-points');
    }

    public function approve(User $user): bool
    {
        return $user->can('loyalty.approve');
    }

    public function viewTransactions(User $user): bool
    {
        return $user->can('loyalty.view-transactions');
    }

    public function manageCampaigns(User $user): bool
    {
        return $user->can('loyalty.manage-campaigns');
    }
}
