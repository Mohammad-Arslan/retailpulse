<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WarehouseRepositoryInterface
{
    public function paginateForBranch(array $filters = [], ?array $accessibleBranchIds = null, int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Warehouse;

    public function create(array $attributes): Warehouse;

    public function update(Warehouse $warehouse, array $attributes): Warehouse;

    public function setDefaultForBranch(int $branchId, int $warehouseId): void;

    public function clearDefaultForBranch(int $branchId): void;

    public function countActiveForBranch(int $branchId): int;

    public function hasOnHandStock(Warehouse $warehouse): bool;

    public function hasOpenTransfers(Warehouse $warehouse): bool;

    public function firstActiveForBranch(int $branchId, ?int $exceptId = null): ?Warehouse;

    public function codeExistsForBranch(int $branchId, string $code, ?int $exceptId = null): bool;

    /**
     * @return list<array{id: int, name: string, code: string, is_default: bool, is_active: bool}>
     */
    public function activeOptionsForBranch(int $branchId): array;

    /**
     * @return list<array{id: int, name: string, code: string, branch_name: string, branch_code: string, label: string}>
     */
    public function allActiveForPicker(?array $accessibleBranchIds = null): array;
}
