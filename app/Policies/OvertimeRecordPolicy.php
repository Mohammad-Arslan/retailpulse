<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OvertimeRecord;
use App\Models\User;

final class OvertimeRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('overtime.view');
    }

    public function view(User $user, OvertimeRecord $overtimeRecord): bool
    {
        return $user->can('overtime.view');
    }

    public function approve(User $user, OvertimeRecord $overtimeRecord): bool
    {
        return $user->can('overtime.approve');
    }

    public function reject(User $user, OvertimeRecord $overtimeRecord): bool
    {
        return $user->can('overtime.approve');
    }
}
