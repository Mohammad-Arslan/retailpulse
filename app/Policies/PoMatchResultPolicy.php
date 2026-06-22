<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PoMatchResult;
use App\Models\User;

final class PoMatchResultPolicy
{
    public function resolve(User $user, PoMatchResult $matchResult): bool
    {
        return $user->can('procurement.resolve-match-exception');
    }
}
