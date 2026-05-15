<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class DashboardService
{
    /**
     * @return array{users: int, roles: int, permissions: int}
     */
    public function stats(): array
    {
        return [
            'users' => $this->modelCount(User::class),
            'roles' => $this->modelCount(Role::class),
            'permissions' => $this->modelCount(Permission::class),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function modelCount(string $modelClass): int
    {
        /** @var Model $model */
        $model = new $modelClass;

        return (int) $model->newQuery()->toBase()->count('*');
    }
}
