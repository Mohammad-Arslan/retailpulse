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
            ['name' => 'admin.dashboard.view', 'description' => 'View admin dashboard (legacy alias)'],
        ],
        'dashboard' => [
            ['name' => 'dashboard.view', 'description' => 'View operational dashboard'],
            ['name' => 'dashboard.view-profit', 'description' => 'View profit-sensitive dashboard widgets'],
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
        'products' => [
            ['name' => 'products.view', 'description' => 'View products and catalog'],
            ['name' => 'products.create', 'description' => 'Create products'],
            ['name' => 'products.update', 'description' => 'Update products'],
            ['name' => 'products.delete', 'description' => 'Delete products'],
            ['name' => 'products.show-cost', 'description' => 'View cost price columns'],
        ],
        'inventory' => [
            ['name' => 'inventory.view', 'description' => 'View stock levels by warehouse'],
            ['name' => 'inventory.receive', 'description' => 'Receive stock into warehouse'],
            ['name' => 'inventory.adjust', 'description' => 'Adjust or write off stock'],
            ['name' => 'inventory.transfer', 'description' => 'Create and process stock transfers'],
        ],
        'settings' => [
            ['name' => 'settings.view', 'description' => 'View global settings'],
            ['name' => 'settings.general.update', 'description' => 'Update general settings'],
            ['name' => 'settings.company.update', 'description' => 'Update company profile settings'],
            ['name' => 'settings.notifications.update', 'description' => 'Update notification settings'],
            ['name' => 'settings.import-export.update', 'description' => 'Update import/export storage settings'],
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
