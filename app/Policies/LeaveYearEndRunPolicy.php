<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveYearEndRun;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class LeaveYearEndRunPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function view(User $user, LeaveYearEndRun $leaveYearEndRun): bool
    {
        if (! $user->can('leave.view')) {
            return false;
        }

        $employee = $leaveYearEndRun->relationLoaded('employee')
            ? $leaveYearEndRun->employee
            : $leaveYearEndRun->employee()->first();

        if ($employee === null) {
            return true;
        }

        return BranchScope::canAccess(
            (int) $employee->primary_branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
