<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Collection;

final class PermissionRepository implements PermissionRepositoryInterface
{
    public function findById(int $id): ?Permission
    {
        return Permission::query()->find($id);
    }

    public function allGrouped(): Collection
    {
        return Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => $permission->group ?? 'general');
    }

    public function create(array $attributes): Permission
    {
        return Permission::query()->create($attributes);
    }

    public function update(Permission $permission, array $attributes): Permission
    {
        $permission->update($attributes);

        return $permission->fresh();
    }

    public function delete(Permission $permission): void
    {
        $permission->delete();
    }
}
