<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

final class PermissionSeeder extends Seeder
{
    /**
     * @var array<string, list<array{name: string, description: string}>>
     */
    private const PERMISSIONS = [
        'admin' => [
            ['name' => 'admin.access', 'description' => 'Access the admin panel'],
            ['name' => 'admin.dashboard.view', 'description' => 'View admin dashboard'],
        ],
        'users' => [
            ['name' => 'users.view', 'description' => 'View users'],
            ['name' => 'users.create', 'description' => 'Create users'],
            ['name' => 'users.update', 'description' => 'Update users'],
            ['name' => 'users.delete', 'description' => 'Delete users'],
            ['name' => 'users.assign-roles', 'description' => 'Assign roles to users'],
            ['name' => 'users.assign-branches', 'description' => 'Assign branches to users'],
        ],
        'roles' => [
            ['name' => 'roles.view', 'description' => 'View roles'],
            ['name' => 'roles.create', 'description' => 'Create roles'],
            ['name' => 'roles.update', 'description' => 'Update roles'],
            ['name' => 'roles.delete', 'description' => 'Delete roles'],
            ['name' => 'roles.clone', 'description' => 'Clone roles'],
            ['name' => 'roles.assign-permissions', 'description' => 'Assign permissions to roles'],
        ],
        'permissions' => [
            ['name' => 'permissions.view', 'description' => 'View permissions'],
            ['name' => 'permissions.create', 'description' => 'Create permissions'],
            ['name' => 'permissions.update', 'description' => 'Update permissions'],
            ['name' => 'permissions.delete', 'description' => 'Delete permissions'],
        ],
        'branches' => [
            ['name' => 'branches.view', 'description' => 'View branches'],
            ['name' => 'branches.create', 'description' => 'Create branches'],
            ['name' => 'branches.update', 'description' => 'Update branches'],
            ['name' => 'branches.delete', 'description' => 'Delete branches'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $permission) {
                Permission::query()->firstOrCreate(
                    ['name' => $permission['name'], 'guard_name' => 'web'],
                    [
                        'group' => $group,
                        'description' => $permission['description'],
                    ],
                );
            }
        }
    }
}
