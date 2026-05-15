<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Permission\CreatePermissionData;
use App\DTOs\Permission\UpdatePermissionData;
use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
    ) {}

    public function create(CreatePermissionData $data): Permission
    {
        return DB::transaction(fn () => $this->permissions->create([
            'name' => $data->name,
            'guard_name' => 'web',
            'group' => $data->group,
            'description' => $data->description,
        ]));
    }

    public function update(Permission $permission, UpdatePermissionData $data): Permission
    {
        return DB::transaction(fn () => $this->permissions->update($permission, [
            'name' => $data->name,
            'group' => $data->group,
            'description' => $data->description,
        ]));
    }

    public function delete(Permission $permission): void
    {
        DB::transaction(fn () => $this->permissions->delete($permission));
    }
}
