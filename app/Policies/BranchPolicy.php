<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Services\BranchContextService;

final class BranchPolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can('branches.view')
            && $this->canAccessModel($user, $branch);
    }

    public function create(User $user): bool
    {
        return $user->can('branches.create')
            && $this->branchContext->accessibleBranchIds($user) === null;
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.update')
            && $this->canAccessModel($user, $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('branches.delete')
            && $this->branchContext->accessibleBranchIds($user) === null;
    }

    private function canAccessModel(User $user, Branch $branch): bool
    {
        $accessibleIds = $this->branchContext->accessibleBranchIds($user);

        if ($accessibleIds === null) {
            return true;
        }

        return in_array($branch->id, $accessibleIds, true);
    }
}
