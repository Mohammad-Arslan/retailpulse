<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BranchRepositoryInterface
{
    public function findById(int $id): ?Branch;

    public function findByCode(string $code): ?Branch;

    /**
     * @param  list<int>|null  $accessibleBranchIds
     */
    public function paginate(array $filters = [], ?array $accessibleBranchIds = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  list<int>|null  $accessibleBranchIds
     */
    public function allActive(?array $accessibleBranchIds = null): Collection;

    public function create(array $attributes): Branch;

    public function update(Branch $branch, array $attributes): Branch;

    public function delete(Branch $branch): void;
}
