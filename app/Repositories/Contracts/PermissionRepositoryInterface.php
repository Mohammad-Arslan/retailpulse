<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Permission;
use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    public function findById(int $id): ?Permission;

    public function allGrouped(): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Permission;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Permission $permission, array $attributes): Permission;

    public function delete(Permission $permission): void;
}
