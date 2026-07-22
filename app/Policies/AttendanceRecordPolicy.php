<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\BranchScope;

final class AttendanceRecordPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.view')
            && $this->canAccessRecordBranch($user, $record);
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.record');
    }

    public function adjust(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.adjust')
            && $this->canAccessRecordBranch($user, $record);
    }

    private function canAccessRecordBranch(User $user, AttendanceRecord $record): bool
    {
        return BranchScope::canAccess(
            (int) $record->branch_id,
            $this->branchContext->accessibleBranchIds($user),
        );
    }
}
