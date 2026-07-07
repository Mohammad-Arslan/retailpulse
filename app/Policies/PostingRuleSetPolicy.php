<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PostingRuleSet;
use App\Models\User;

final class PostingRuleSetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.view') || $user->can('accounting.manage-posting-rules');
    }

    public function view(User $user, PostingRuleSet $postingRuleSet): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PostingRuleSet $postingRuleSet): bool
    {
        return $user->can('accounting.manage-posting-rules');
    }
}
