<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\BranchContextService;

final class WarehousePolicy
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('warehouses.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.view')
            && $this->canAccessBranch($user, $warehouse->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.update')
            && $this->canAccessBranch($user, $warehouse->branch_id);
    }

    public function deactivate(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.deactivate')
            && $this->canAccessBranch($user, $warehouse->branch_id);
    }

    public function manageBins(User $user, Warehouse $warehouse): bool
    {
        return $user->can('inventory.manage-bins')
            && $this->canAccessBranch($user, $warehouse->branch_id);
    }

    private function canAccessBranch(User $user, int $branchId): bool
    {
        $accessibleIds = $this->branchContext->accessibleBranchIds($user);

        if ($accessibleIds === null) {
            return true;
        }

        return in_array($branchId, $accessibleIds, true);
    }
}
