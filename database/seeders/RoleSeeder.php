<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const ACCOUNTING_PERMISSIONS = [
        'accounting.view',
        'accounting.manage-coa',
        'accounting.manage-mappings',
        'accounting.manage-posting-rules',
        'accounting.create-journal',
        'accounting.approve-journal',
        'accounting.post-journal',
        'accounting.reverse-journal',
        'accounting.import-coa',
        'accounting.import-opening-balances',
        'accounting.manage-cost-centres',
        'accounting.manage-fiscal-years',
        'accounting.close-fiscal-year',
        'accounting.reopen-fiscal-year',
        'accounting.manage-bank-accounts',
        'accounting.import-bank-statements',
        'accounting.reconcile-bank',
        'accounting.manage-petty-cash',
        'accounting.approve-petty-cash',
        'accounting.manage-cheques',
        'accounting.manage-assets',
        'accounting.manage-tax-settings',
        'accounting.view-reports',
        'accounting.export-reports',
    ];

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
                'dashboard.view',
                'dashboard.view-profit',
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
                'warehouses.view',
                'warehouses.create',
                'warehouses.update',
                'warehouses.deactivate',
                'products.view',
                'products.create',
                'products.update',
                'products.delete',
                'products.show-cost',
                'products.import',
                'products.export',
                'inventory.view',
                'inventory.reports',
                'inventory.receive',
                'inventory.adjust',
                'inventory.transfer',
                'inventory.import-opening-stock',
                'inventory.bulk-adjustment-import',
                'inventory.manage-bins',
                'inventory.release-quarantine',
                'inventory.cycle-count',
                'inventory.cycle-count.approve',
                'settings.view',
                'settings.general.update',
                'settings.company.update',
                'settings.notifications.update',
                'settings.import-export.update',
                'settings.tax.update',
                'settings.checkout.update',
                'settings.fbr.update',
                'settings.inventory.update',
                'pos.access',
                'pos.discount',
                'pos.approve-discount',
                'pos.suspend-cart',
                'pos.void-cart',
                'pos.override-stock',
                'pos.admin',
                'sales.view',
                'sales.export',
                'sales.import-historical',
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.delete',
                'customers.view-credit',
                'customers.import',
                'customers.export',
                'customers.write-off-debt',
                'loyalty.view',
                'loyalty.manage',
                'loyalty.manage-programs',
                'loyalty.manage-rules',
                'loyalty.adjust-points',
                'loyalty.approve',
                'loyalty.view-transactions',
                'loyalty.manage-campaigns',
                'procurement.view',
                'procurement.create',
                'procurement.update',
                'procurement.delete',
                'procurement.approve-po',
                'procurement.resolve-match-exception',
                'procurement.receive-grn',
                'procurement.manage-suppliers',
                'procurement.process-payments',
                'procurement.manage-returns',
                'suppliers.import',
                'suppliers.export',
                'supplier-price-lists.import',
                'supplier-price-lists.export',
                'settings.procurement.update',
                ...self::ACCOUNTING_PERMISSIONS,
            ],
        ],
        'branch-manager' => [
            'description' => 'Branch operations',
            'is_system' => true,
            'permissions' => [
                'admin.access',
                'admin.dashboard.view',
                'dashboard.view',
                'branches.view',
                'branches.update',
                'warehouses.view',
                'warehouses.create',
                'warehouses.update',
                'warehouses.deactivate',
                'users.view',
                'products.view',
                'products.create',
                'products.update',
                'inventory.view',
                'inventory.reports',
                'inventory.receive',
                'inventory.adjust',
                'inventory.transfer',
                'inventory.import-opening-stock',
                'inventory.bulk-adjustment-import',
                'inventory.manage-bins',
                'inventory.release-quarantine',
                'inventory.cycle-count',
                'inventory.cycle-count.approve',
                'settings.view',
                'settings.tax.update',
                'settings.checkout.update',
                'sales.view',
                'sales.export',
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.view-credit',
                'customers.import',
                'customers.export',
                'loyalty.view',
                'loyalty.view-transactions',
                'loyalty.approve',
                'procurement.view',
                'procurement.create',
                'procurement.update',
                'procurement.approve-po',
                'procurement.resolve-match-exception',
                'procurement.receive-grn',
                'procurement.manage-suppliers',
                'procurement.manage-returns',
                'suppliers.import',
                'suppliers.export',
                'supplier-price-lists.import',
                'supplier-price-lists.export',
            ],
        ],
        'accountant' => [
            'description' => 'Finance modules',
            'is_system' => true,
            'permissions' => [
                'admin.access',
                'admin.dashboard.view',
                'dashboard.view',
                'sales.view',
                'sales.export',
                'procurement.view',
                'procurement.process-payments',
                ...self::ACCOUNTING_PERMISSIONS,
            ],
        ],
        'cashier' => [
            'description' => 'POS only',
            'is_system' => true,
            'permissions' => [
                'pos.access',
                'pos.discount',
                'pos.suspend-cart',
                'pos.void-cart',
                'customers.view',
            ],
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
