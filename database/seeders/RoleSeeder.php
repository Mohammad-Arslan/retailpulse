<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AccessControlLabels;
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
        'accounting.manage-modules',
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
     * @var list<string>
     */
    private const EXPENSE_ACCOUNTANT_PERMISSIONS = [
        'expenses.view',
        'expenses.approve',
        'expenses.post',
        'expenses.reverse',
    ];

    /**
     * @var list<string>
     */
    private const HR_MANAGER_PERMISSIONS = [
        'admin.access',
        'admin.dashboard.view',
        'hr.view-employees',
        'hr.manage-employees',
        'employees.import',
        'employees.export',
        'hr.manage-org',
        'holiday.manage',
        'attendance.view',
        'attendance.record',
        'attendance.adjust',
        'attendance.manage-sources',
        'leave.view',
        'leave.request',
        'leave.approve',
        'leave.manage-types',
        'leave.manage-policies',
        'overtime.view',
        'overtime.approve',
        'overtime.manage-policies',
        'expenses.view',
        'expenses.create',
        'expenses.approve',
        'expenses.manage-categories',
        'expenses.manage-recurring',
        'selfservice.view-own',
    ];

    /**
     * @var list<string>
     */
    private const PAYROLL_OFFICER_PERMISSIONS = [
        'admin.access',
        'admin.dashboard.view',
        'hr.view-employees',
        'payroll.view',
        'payroll.process',
        'payroll.approve',
        'payroll.post',
        'payroll.reverse',
        'payroll.manage-components',
        'payroll.manage-structures',
        'payroll.manage-statutory',
        'payroll.manage-tax-slabs',
        'overtime.view',
        'leave.view',
        'attendance.view',
    ];

    /**
     * @var list<string>
     */
    private const LINE_MANAGER_PERMISSIONS = [
        'admin.access',
        'admin.dashboard.view',
        'hr.view-employees',
        'attendance.view',
        'attendance.record',
        'leave.view',
        'leave.approve',
        'overtime.view',
        'overtime.approve',
        'expenses.view',
        'expenses.approve',
        'selfservice.view-own',
    ];

    /**
     * @var list<string>
     */
    private const EMPLOYEE_ROLE_PERMISSIONS = [
        'selfservice.view-own',
        'leave.view',
        'leave.request',
        'attendance.view',
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
                'dashboard.sales.view',
                'dashboard.inventory.view',
                'dashboard.finance.view',
                'dashboard.procurement.view',
                'dashboard.operations.view',
                'dashboard.exceptions.view',
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
                'branches.access-all',
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
                ...self::HR_MANAGER_PERMISSIONS,
                ...self::PAYROLL_OFFICER_PERMISSIONS,
                'expenses.create',
                'expenses.post',
                'expenses.reverse',
                'expenses.manage-categories',
                'expenses.manage-recurring',
            ],
        ],
        'branch-manager' => [
            'description' => 'Branch operations',
            'is_system' => true,
            'permissions' => [
                'admin.access',
                'admin.dashboard.view',
                'dashboard.view',
                'dashboard.sales.view',
                'dashboard.inventory.view',
                'dashboard.procurement.view',
                'dashboard.operations.view',
                'dashboard.exceptions.view',
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
                ...self::LINE_MANAGER_PERMISSIONS,
            ],
        ],
        'accountant' => [
            'description' => 'Finance modules',
            'is_system' => true,
            'permissions' => [
                'admin.access',
                'admin.dashboard.view',
                'dashboard.view',
                'dashboard.finance.view',
                'dashboard.procurement.view',
                'dashboard.exceptions.view',
                'sales.view',
                'sales.export',
                'procurement.view',
                'procurement.process-payments',
                ...self::ACCOUNTING_PERMISSIONS,
                ...self::EXPENSE_ACCOUNTANT_PERMISSIONS,
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
        'hr-manager' => [
            'description' => 'HR manager',
            'is_system' => true,
            'permissions' => self::HR_MANAGER_PERMISSIONS,
        ],
        'payroll-officer' => [
            'description' => 'Payroll officer',
            'is_system' => true,
            'permissions' => self::PAYROLL_OFFICER_PERMISSIONS,
        ],
        'line-manager' => [
            'description' => 'Line manager',
            'is_system' => true,
            'permissions' => self::LINE_MANAGER_PERMISSIONS,
        ],
        'employee' => [
            'description' => 'Employee self-service',
            'is_system' => true,
            'permissions' => self::EMPLOYEE_ROLE_PERMISSIONS,
        ],
    ];

    public function run(): void
    {
        $allPermissions = Permission::query()->pluck('name')->all();

        foreach (self::ROLES as $name => $config) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'display_name' => AccessControlLabels::forRole($name),
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
