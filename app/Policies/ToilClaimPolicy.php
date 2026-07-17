<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ToilClaim;
use App\Models\User;

final class ToilClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('overtime.view') || $user->can('leave.view');
    }

    public function view(User $user, ToilClaim $toilClaim): bool
    {
        return $user->can('overtime.view') || $user->can('leave.view');
    }

    public function create(User $user): bool
    {
        return $user->can('toil.request-cash-claim');
    }

    public function approve(User $user, ToilClaim $toilClaim): bool
    {
        return $user->can('toil.approve-cash-claim');
    }

    public function reject(User $user, ToilClaim $toilClaim): bool
    {
        return $user->can('toil.approve-cash-claim');
    }

    public function cancel(User $user, ToilClaim $toilClaim): bool
    {
        return $user->can('toil.approve-cash-claim') || $user->can('toil.request-cash-claim');
    }
}
