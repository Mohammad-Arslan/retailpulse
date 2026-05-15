<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    /**
     * @var array<string, array{description: string, is_system: bool, permissions: list<string>}>
     */
    private const ROLES = [
        'super-admin' => [
            'description' => 'Full system access',
            'is_system' => true,
            'permissions' => [],
        ],
        'owner' => [
            'description' => 'Business owner',
            'is_system' => true,
            'permissions' => [
                'admin.access',
                'admin.dashboard.view',
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'users.assign-roles',
                'users.assign-branches',
                'branches.view',
                'branches.create',
                'branches.update',
                'branches.delete',
                'products.view',
                'products.create',
                'products.update',
                'products.delete',
                'products.show-cost',
                'inventory.view',
                'inventory.receive',
                'inventory.adjust',
                'inventory.transfer',
            ],
        ],
        'branch-manager' => [
            'description' => 'Branch operations',
            'is_system' => true,
            'permissions' => [
                'admin.access',
                'admin.dashboard.view',
                'branches.view',
                'branches.update',
                'users.view',
                'products.view',
                'products.create',
                'products.update',
                'inventory.view',
                'inventory.receive',
                'inventory.adjust',
                'inventory.transfer',
            ],
        ],
        'accountant' => [
            'description' => 'Finance modules',
            'is_system' => true,
            'permissions' => [
                'admin.access',
                'admin.dashboard.view',
            ],
        ],
        'cashier' => [
            'description' => 'POS only',
            'is_system' => true,
            'permissions' => [],
        ],
    ];

    public function run(): void
    {
        $allPermissions = Permission::query()->pluck('name')->all();

        foreach (self::ROLES as $name => $config) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'description' => $config['description'],
                    'is_system' => $config['is_system'],
                ],
            );

            $permissions = $name === 'super-admin'
                ? $allPermissions
                : $config['permissions'];

            $role->syncPermissions($permissions);
        }
    }
}
