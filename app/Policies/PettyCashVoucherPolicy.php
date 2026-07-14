<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PettyCashVoucher;
use App\Models\User;

final class PettyCashVoucherPolicy
{
    public function create(User $user): bool
    {
        return $user->can('accounting.manage-petty-cash');
    }

    public function approve(User $user, PettyCashVoucher $pettyCashVoucher): bool
    {
        return $user->can('accounting.approve-petty-cash');
    }

    public function reject(User $user, PettyCashVoucher $pettyCashVoucher): bool
    {
        return $user->can('accounting.approve-petty-cash');
    }
}
