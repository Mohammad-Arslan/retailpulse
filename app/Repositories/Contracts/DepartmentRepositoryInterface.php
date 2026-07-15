<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DepartmentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, Department>
     */
    public function activeForSelect(?int $legalEntityId = null): Collection;

    public function create(array $attributes): Department;

    public function update(Department $department, array $attributes): Department;

    public function hasActiveEmployees(Department $department): bool;

    public function parentIdOf(int $departmentId): ?int;
}
