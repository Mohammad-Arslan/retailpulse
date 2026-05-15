<?php

declare(strict_types=1);

namespace App\Support;

final class BranchContext
{
    /**
     * @param  list<int>|null  $accessibleBranchIds  null = all branches (head office)
     */
    public function __construct(
        public readonly ?int $branchId,
        public readonly ?array $accessibleBranchIds,
    ) {}

    public function isAllBranches(): bool
    {
        return $this->branchId === null;
    }

    public function isRestricted(): bool
    {
        return $this->accessibleBranchIds !== null;
    }

    public function canAccessBranch(int $branchId): bool
    {
        if ($this->accessibleBranchIds === null) {
            return true;
        }

        return in_array($branchId, $this->accessibleBranchIds, true);
    }
}
