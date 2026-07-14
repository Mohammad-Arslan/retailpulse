<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Role\CreateRoleData;
use App\DTOs\Role\UpdateRoleData;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Support\AccessControlLabels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {}

    public function create(CreateRoleData $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = $this->roles->create([
                'name' => $data->name,
                'display_name' => $data->displayName,
                'guard_name' => 'web',
                'description' => $data->description,
                'is_system' => false,
            ]);

            $role->syncPermissions($data->permissionNames);

            return $role->load('permissions');
        });
    }

    public function update(Role $role, UpdateRoleData $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role = $this->roles->update($role, [
                'name' => $data->name,
                'display_name' => $data->displayName,
                'description' => $data->description,
            ]);

            $role->syncPermissions($data->permissionNames);

            return $role->load('permissions');
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw new \DomainException(__('roles.cannot_delete_system'));
        }

        DB::transaction(fn () => $this->roles->delete($role));
    }

    public function clone(Role $source, string $newName): Role
    {
        return DB::transaction(function () use ($source, $newName) {
            $role = $this->roles->create([
                'name' => $newName,
                'display_name' => $source->display_name
                    ? $source->display_name.' (Copy)'
                    : AccessControlLabels::forRole($newName),
                'guard_name' => $source->guard_name,
                'description' => $source->description
                    ? __('roles.cloned_from', ['name' => $source->display_name ?: $source->name])
                    : null,
                'is_system' => false,
            ]);

            $role->syncPermissions($source->permissions->pluck('name')->all());

            return $role->load('permissions');
        });
    }

    public function slugFromName(string $name): string
    {
        return Str::slug($name);
    }
}
