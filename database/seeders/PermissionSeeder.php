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
        'warehouses' => [
            ['name' => 'warehouses.view', 'description' => 'View warehouses'],
            ['name' => 'warehouses.create', 'description' => 'Create warehouses'],
            ['name' => 'warehouses.update', 'description' => 'Update warehouses'],
            ['name' => 'warehouses.deactivate', 'description' => 'Deactivate warehouses'],
        ],
        'products' => [
            ['name' => 'products.view', 'description' => 'View products and catalog'],
            ['name' => 'products.create', 'description' => 'Create products'],
            ['name' => 'products.update', 'description' => 'Update products'],
            ['name' => 'products.delete', 'description' => 'Delete products'],
            ['name' => 'products.show-cost', 'description' => 'View cost price columns'],
            ['name' => 'products.import', 'description' => 'Import catalog data from spreadsheets'],
            ['name' => 'products.export', 'description' => 'Export catalog data to spreadsheets'],
        ],
        'inventory' => [
            ['name' => 'inventory.view', 'description' => 'View stock levels by warehouse'],
            ['name' => 'inventory.reports', 'description' => 'View and export stock reports'],
            ['name' => 'inventory.receive', 'description' => 'Receive stock into warehouse'],
            ['name' => 'inventory.adjust', 'description' => 'Adjust or write off stock'],
            ['name' => 'inventory.transfer', 'description' => 'Create and process stock transfers'],
            ['name' => 'inventory.import-opening-stock', 'description' => 'Import opening stock balances from spreadsheets'],
            ['name' => 'inventory.bulk-adjustment-import', 'description' => 'Bulk import stock adjustments from spreadsheets'],
            ['name' => 'inventory.manage-bins', 'description' => 'Manage warehouse zones and bin locations'],
            ['name' => 'inventory.release-quarantine', 'description' => 'Release or scrap quarantined stock'],
            ['name' => 'inventory.cycle-count', 'description' => 'Create and manage cycle count sessions'],
            ['name' => 'inventory.cycle-count.approve', 'description' => 'Approve cycle count variances'],
        ],
        'settings' => [
            ['name' => 'settings.view', 'description' => 'View global settings'],
            ['name' => 'settings.general.update', 'description' => 'Update general settings'],
            ['name' => 'settings.company.update', 'description' => 'Update company profile settings'],
            ['name' => 'settings.notifications.update', 'description' => 'Update notification settings'],
            ['name' => 'settings.import-export.update', 'description' => 'Update import/export storage settings'],
            ['name' => 'settings.tax.update', 'description' => 'Update tax calculation settings'],
            ['name' => 'settings.checkout.update', 'description' => 'Update checkout, payment, and invoice settings'],
            ['name' => 'settings.fbr.update', 'description' => 'Update FBR IRIS integration settings'],
            ['name' => 'settings.inventory.update', 'description' => 'Update inventory reservation and count settings'],
            ['name' => 'settings.procurement.update', 'description' => 'Update procurement and supplier settings'],
        ],
        'pos' => [
            ['name' => 'pos.access', 'description' => 'Enter and operate the POS screen'],
            ['name' => 'pos.discount', 'description' => 'Apply discounts up to 30% on line items'],
            ['name' => 'pos.approve-discount', 'description' => 'Approve discounts above 30%'],
            ['name' => 'pos.suspend-cart', 'description' => 'Suspend and resume carts'],
            ['name' => 'pos.void-cart', 'description' => 'Void any cart'],
            ['name' => 'pos.override-stock', 'description' => 'Override out-of-stock warning with manager PIN'],
            ['name' => 'pos.admin', 'description' => 'Reset PIN lockouts and manage POS sessions'],
        ],
        'sales' => [
            ['name' => 'sales.view', 'description' => 'View sale records and invoices'],
            ['name' => 'sales.import-historical', 'description' => 'Import historical sales data'],
            ['name' => 'sales.export', 'description' => 'Export sale records'],
            ['name' => 'sales.refund', 'description' => 'Process sale refunds'],
        ],
        'customers' => [
            ['name' => 'customers.view', 'description' => 'View customers and POS search'],
            ['name' => 'customers.create', 'description' => 'Create customers'],
            ['name' => 'customers.update', 'description' => 'Update customers and wallet top-ups'],
            ['name' => 'customers.delete', 'description' => 'Delete customers'],
            ['name' => 'customers.view-credit', 'description' => 'View customer credit limits and AR balances'],
            ['name' => 'customers.import', 'description' => 'Import customers from spreadsheets'],
            ['name' => 'customers.export', 'description' => 'Export customers to spreadsheets'],
            ['name' => 'customers.write-off-debt', 'description' => 'Write off customer bad debt'],
        ],
        'loyalty' => [
            ['name' => 'loyalty.view', 'description' => 'View loyalty programs and customer wallets'],
            ['name' => 'loyalty.manage', 'description' => 'Manage loyalty engine settings'],
            ['name' => 'loyalty.manage-programs', 'description' => 'Create and update loyalty programs'],
            ['name' => 'loyalty.manage-rules', 'description' => 'Manage loyalty earning and redemption rules'],
            ['name' => 'loyalty.adjust-points', 'description' => 'Manually adjust customer loyalty points'],
            ['name' => 'loyalty.approve', 'description' => 'Approve loyalty transactions requiring authorization'],
            ['name' => 'loyalty.view-transactions', 'description' => 'View loyalty transaction history'],
            ['name' => 'loyalty.manage-campaigns', 'description' => 'Manage loyalty campaigns'],
        ],
        'procurement' => [
            ['name' => 'procurement.view', 'description' => 'View procurement documents and suppliers'],
            ['name' => 'procurement.create', 'description' => 'Create purchase orders and requisitions'],
            ['name' => 'procurement.update', 'description' => 'Update draft procurement documents'],
            ['name' => 'procurement.delete', 'description' => 'Delete draft procurement documents'],
            ['name' => 'procurement.approve-po', 'description' => 'Approve purchase orders above threshold'],
            ['name' => 'procurement.resolve-match-exception', 'description' => 'Resolve three-way match exceptions'],
            ['name' => 'procurement.receive-grn', 'description' => 'Post goods receiving notes'],
            ['name' => 'procurement.manage-suppliers', 'description' => 'Manage supplier master data'],
            ['name' => 'procurement.process-payments', 'description' => 'Record supplier payments'],
            ['name' => 'procurement.manage-returns', 'description' => 'Manage purchase returns and RMA'],
            ['name' => 'suppliers.import', 'description' => 'Import suppliers from spreadsheets'],
            ['name' => 'suppliers.export', 'description' => 'Export suppliers to spreadsheets'],
            ['name' => 'supplier-price-lists.import', 'description' => 'Import supplier price lists from spreadsheets'],
            ['name' => 'supplier-price-lists.export', 'description' => 'Export supplier price lists to spreadsheets'],
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
