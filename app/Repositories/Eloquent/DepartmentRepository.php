<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Department;
use App\Models\Employee;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class DepartmentRepository implements DepartmentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['name', 'code', 'status'], true)
            ? $filters['sort']
            : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Department::query()
            ->with(['parent:id,name', 'legalEntity:id,legal_name'])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['legal_entity_id'] ?? null, fn ($q, $id) => $q->where('legal_entity_id', (int) $id))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeForSelect(?int $legalEntityId = null): Collection
    {
        return Department::query()
            ->where('status', 'active')
            ->when($legalEntityId !== null, fn ($q) => $q->where('legal_entity_id', $legalEntityId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'legal_entity_id']);
    }

    public function create(array $attributes): Department
    {
        return Department::query()->create($attributes);
    }

    public function update(Department $department, array $attributes): Department
    {
        $department->update($attributes);

        return $department->fresh(['parent', 'legalEntity', 'costCentre']) ?? $department;
    }

    public function hasActiveEmployees(Department $department): bool
    {
        return Employee::query()
            ->where('department_id', $department->id)
            ->where('status', 'active')
            ->exists();
    }

    public function parentIdOf(int $departmentId): ?int
    {
        $parentId = Department::query()->whereKey($departmentId)->value('parent_id');

        return $parentId !== null ? (int) $parentId : null;
    }
}
