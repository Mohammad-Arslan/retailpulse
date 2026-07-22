<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OvertimeRecord;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class OvertimeRecordPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('overtime.view');
    }

    public function view(User $user, OvertimeRecord $overtimeRecord): bool
    {
        return $user->can('overtime.view')
            && $this->canAccessRecordBranch($user, $overtimeRecord);
    }

    public function approve(User $user, OvertimeRecord $overtimeRecord): bool
    {
        return $user->can('overtime.approve')
            && $this->canAccessRecordBranch($user, $overtimeRecord);
    }

    public function reject(User $user, OvertimeRecord $overtimeRecord): bool
    {
        return $user->can('overtime.approve')
            && $this->canAccessRecordBranch($user, $overtimeRecord);
    }

    private function canAccessRecordBranch(User $user, OvertimeRecord $overtimeRecord): bool
    {
        $employee = $overtimeRecord->relationLoaded('employee')
            ? $overtimeRecord->employee
            : $overtimeRecord->employee()->first();

        if ($employee === null) {
            return true;
        }

        return BranchScope::canAccess(
            (int) $employee->primary_branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
