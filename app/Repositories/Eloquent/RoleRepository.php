<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class RoleRepository implements RoleRepositoryInterface
{
    public function findById(int $id): ?Role
    {
        return Role::query()->with('permissions')->find($id);
    }

    public function findByName(string $name): ?Role
    {
        return Role::query()->where('name', $name)->first();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Role::query()->withCount('permissions');

        $sort = $filters['sort'] ?? 'name';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'display_name', 'description', 'created_at'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'display_name';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function allWithPermissions(): Collection
    {
        return Role::query()->with('permissions')->orderBy('display_name')->orderBy('name')->get();
    }

    public function create(array $attributes): Role
    {
        return Role::query()->create($attributes);
    }

    public function update(Role $role, array $attributes): Role
    {
        $role->update($attributes);

        return $role->fresh(['permissions']);
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
