<?php

declare(strict_types=1);

namespace App\Services\Navigation\Catalog;

use App\Services\Navigation\NavigationItem;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Navigation\NavigationSection;

/**
 * Registers the default admin navigation tree extracted from the former adminNav.js.
 * Future modules can append via NavigationRegistry::registerItems() from their own providers.
 */
final class AdminNavigationCatalog
{
    public static function register(NavigationRegistry $registry): void
    {
        $registry->registerSection(new NavigationSection('overview', 'overview', 10, [
            self::item('dashboard', 'dashboard', 'admin.dashboard', 'layout-dashboard', 'overview', 10, [
                'permission' => 'admin.dashboard.view',
                'permissionsAny' => ['admin.dashboard.view', 'dashboard.view'],
                'routePattern' => 'admin.dashboard',
                'keywords' => ['home', 'overview', 'stats'],
            ]),
            self::item('pos', 'pos', 'admin.pos.index', 'monitor', 'overview', 20, [
                'permission' => 'pos.access',
                'routePattern' => 'admin.pos.*',
                'keywords' => ['pos', 'cashier', 'register', 'checkout', 'sale'],
            ]),
            self::item('sales', 'sales', 'admin.sales.index', 'receipt', 'overview', 30, [
                'permission' => 'sales.view',
                'routePattern' => 'admin.sales.*',
                'keywords' => ['sales', 'invoices', 'transactions', 'receipts'],
            ]),
        ]));

        $registry->registerSection(new NavigationSection('organization', 'organization', 20, [
            self::item('branches', 'branches', 'admin.branches.index', 'building-2', 'organization', 10, [
                'permission' => 'branches.view',
                'routePattern' => 'admin.branches.*',
                'keywords' => ['stores', 'locations', 'outlets'],
            ]),
            self::item('warehouses', 'warehouses', 'admin.warehouses.index', 'boxes', 'organization', 20, [
                'permission' => 'warehouses.view',
                'routePattern' => 'admin.warehouses.*',
                'keywords' => ['storage', 'depot', 'location'],
            ]),
        ]));

        $registry->registerSection(new NavigationSection('inventory', 'inventorySection', 30, [
            self::item('inventory', 'inventory', 'admin.inventory.index', 'warehouse', 'inventory', 10, [
                'permission' => 'inventory.view',
                'routePattern' => 'admin.inventory.*',
                'activeRoutes' => [
                    'admin.inventory.index',
                    'admin.inventory.adjust',
                    'admin.inventory.receive',
                ],
                'keywords' => ['stock', 'warehouse', 'on hand', 'quantity'],
            ]),
            self::item('stock-transfers', 'stockTransfers', 'admin.stock-transfers.index', 'truck', 'inventory', 20, [
                'permission' => 'inventory.transfer',
                'routePattern' => 'admin.stock-transfers.*',
                'keywords' => ['transfer', 'ship', 'receive'],
            ]),
            self::item('suppliers', 'suppliers', 'admin.suppliers.index', 'user-round', 'inventory', 30, [
                'permission' => 'procurement.manage-suppliers',
                'routePattern' => 'admin.suppliers.*',
                'keywords' => ['supplier', 'vendor', 'procurement'],
            ]),
            self::item('purchase-orders', 'purchaseOrders', 'admin.purchase-orders.index', 'clipboard-list', 'inventory', 40, [
                'permission' => 'procurement.view',
                'routePattern' => 'admin.purchase-orders.*',
                'keywords' => ['po', 'purchase', 'order', 'procurement'],
            ]),
            self::item('goods-receiving', 'goodsReceiving', 'admin.goods-receiving-notes.index', 'package', 'inventory', 50, [
                'permission' => 'procurement.view',
                'routePattern' => 'admin.goods-receiving-notes.*',
                'keywords' => ['grn', 'receiving', 'receipt', 'procurement'],
            ]),
            self::item('supplier-price-lists', 'supplierPriceLists', 'admin.supplier-price-lists.index', 'tag', 'inventory', 60, [
                'permission' => 'procurement.manage-suppliers',
                'routePattern' => 'admin.supplier-price-lists.*',
                'keywords' => ['price list', 'contract', 'supplier pricing', 'procurement'],
            ]),
            self::item('procurement-reports', 'procurementReports', 'admin.procurement.reports', 'clipboard-list', 'inventory', 70, [
                'permission' => 'procurement.view',
                'routePattern' => 'admin.procurement.reports',
                'keywords' => ['procurement', 'reports', 'payables', 'open po'],
            ]),
            self::item('bin-stock', 'binStock', 'admin.inventory.bin-report', 'boxes', 'inventory', 80, [
                'permission' => 'inventory.reports',
                'routePattern' => 'admin.inventory.bin-report',
                'keywords' => ['bin', 'location', 'zone', 'aisle'],
            ]),
            self::item('bin-transfer', 'binTransfer', 'admin.inventory.bin-transfer.form', 'truck', 'inventory', 90, [
                'permission' => 'inventory.manage-bins',
                'routePattern' => 'admin.inventory.bin-transfer.form',
                'keywords' => ['bin', 'transfer', 'move', 'relocate'],
            ]),
            self::item('branch-stock-settings', 'branchStockSettings', 'admin.inventory.branch-stock-settings', 'scale', 'inventory', 100, [
                'permission' => 'inventory.adjust',
                'routePattern' => 'admin.inventory.branch-stock-settings',
                'keywords' => ['reorder', 'safety stock', 'branch', 'threshold'],
            ]),
            self::item('quarantine', 'quarantine', 'admin.inventory.quarantine', 'shield-alert', 'inventory', 110, [
                'permission' => 'inventory.release-quarantine',
                'routePattern' => 'admin.inventory.quarantine',
                'keywords' => ['qc', 'quality', 'hold', 'pending'],
            ]),
            self::item('cycle-counts', 'cycleCounts', 'admin.count-sessions.index', 'clipboard-list', 'inventory', 120, [
                'permission' => 'inventory.cycle-count',
                'routePattern' => 'admin.count-sessions.*',
                'keywords' => ['count', 'stocktake', 'physical', 'variance'],
            ]),
            self::item('count-schedules', 'countSchedules', 'admin.count-schedule-rules.index', 'calendar-clock', 'inventory', 130, [
                'permission' => 'inventory.cycle-count',
                'routePattern' => 'admin.count-schedule-rules.*',
                'keywords' => ['schedule', 'recurring', 'automatic', 'count'],
            ]),
        ]));

        $registry->registerSection(new NavigationSection('catalog', 'catalog', 40, [
            self::item('products', 'products', 'admin.products.index', 'package', 'catalog', 10, [
                'permission' => 'products.view',
                'routePattern' => 'admin.products.*',
                'keywords' => ['items', 'sku', 'inventory', 'pim'],
            ]),
            self::item('categories', 'categories', 'admin.categories.index', 'folder-tree', 'catalog', 20, [
                'permission' => 'products.view',
                'routePattern' => 'admin.categories.*',
                'keywords' => ['taxonomy', 'groups'],
            ]),
            self::item('brands', 'brands', 'admin.brands.index', 'tag', 'catalog', 30, [
                'permission' => 'products.view',
                'routePattern' => 'admin.brands.*',
                'keywords' => ['manufacturer', 'vendor'],
            ]),
            self::item('units', 'units', 'admin.units.index', 'scale', 'catalog', 40, [
                'permission' => 'products.view',
                'routePattern' => 'admin.units.*',
                'keywords' => ['measurement', 'uom', 'kg', 'liter', 'each'],
            ]),
        ]));

        $registry->registerSection(new NavigationSection('customers', 'customersSection', 50, [
            self::item('customers', 'customers', 'admin.customers.index', 'user-round', 'customers', 10, [
                'permission' => 'customers.view',
                'routePattern' => 'admin.customers.*',
                'keywords' => ['crm', 'loyalty', 'wallet', 'credit'],
            ]),
            self::item('customer-groups', 'customerGroups', 'admin.customer-groups.index', 'users-round', 'customers', 20, [
                'permission' => 'customers.view',
                'routePattern' => 'admin.customer-groups.*',
                'keywords' => ['wholesale', 'vip', 'pricing group'],
            ]),
            self::item('ar-aging', 'arAging', 'admin.ar-aging.index', 'timer', 'customers', 30, [
                'permission' => 'customers.view-credit',
                'routePattern' => 'admin.ar-aging.*',
                'keywords' => ['receivable', 'outstanding', 'aging', 'credit'],
            ]),
            self::item('loyalty-programs', 'loyaltyPrograms', 'admin.loyalty.programs.index', 'gift', 'customers', 40, [
                'permission' => 'loyalty.view',
                'routePattern' => 'admin.loyalty.programs.*',
                'keywords' => ['rewards', 'points', 'tiers', 'campaigns'],
            ]),
            self::item('loyalty-reports', 'loyaltyReports', 'admin.loyalty.reports.index', 'receipt', 'customers', 50, [
                'permission' => 'loyalty.view',
                'routePattern' => 'admin.loyalty.reports.*',
                'keywords' => ['points earned', 'redemption', 'tier distribution'],
            ]),
            self::item('loyalty-transactions', 'loyaltyTransactions', 'admin.loyalty.transactions.index', 'clipboard-list', 'customers', 60, [
                'permission' => 'loyalty.view-transactions',
                'routePattern' => 'admin.loyalty.transactions.*',
                'keywords' => ['points', 'approval', 'redemption', 'adjustment'],
            ]),
        ]));

        $registry->registerSection(new NavigationSection('hr', 'hrSection', 55, [
            self::item('employees', 'employees', 'admin.hr.employees.index', 'users', 'hr', 10, [
                'permission' => 'hr.view-employees',
                'permissionsAny' => ['hr.view-employees', 'hr.manage-employees'],
                'module' => 'hr',
                'routePattern' => 'admin.hr.employees.*',
                'keywords' => ['employees', 'staff', 'hr', 'payroll'],
            ]),
            self::item('expenses', 'expenses', 'admin.expenses.expenses.index', 'receipt', 'expenses', 20, [
                'permission' => 'expenses.view',
                'permissionsAny' => ['expenses.view', 'expenses.create'],
                'module' => 'expenses',
                'routePattern' => 'admin.expenses.expenses.*',
                'keywords' => ['expense', 'voucher', 'receipt'],
            ]),
            self::item('expense-categories', 'expenseCategories', 'admin.expenses.expense-categories.index', 'folder-tree', 'expenses', 30, [
                'permission' => 'expenses.manage-categories',
                'permissionsAny' => ['expenses.manage-categories', 'expenses.view'],
                'module' => 'expenses',
                'routePattern' => 'admin.expenses.expense-categories.*',
                'keywords' => ['category', 'expense type'],
            ]),
            self::item('recurring-expenses', 'recurringExpenses', 'admin.expenses.recurring-expenses.index', 'calendar-clock', 'expenses', 40, [
                'permission' => 'expenses.manage-recurring',
                'permissionsAny' => ['expenses.manage-recurring', 'expenses.view'],
                'module' => 'expenses',
                'routePattern' => 'admin.expenses.recurring-expenses.*',
                'keywords' => ['recurring', 'schedule', 'rent', 'subscription'],
            ]),
            self::item('attendance-records', 'attendanceRecords', 'admin.attendance.records.index', 'clock', 'attendance', 50, [
                'permission' => 'attendance.view',
                'permissionsAny' => ['attendance.view', 'attendance.record'],
                'module' => 'attendance',
                'routePattern' => 'admin.attendance.records.*',
                'keywords' => ['attendance', 'clock in', 'clock out', 'timesheet'],
            ]),
            self::item('attendance-sources', 'attendanceSources', 'admin.attendance.sources.index', 'plug', 'attendance', 60, [
                'permission' => 'attendance.manage-sources',
                'permissionsAny' => ['attendance.manage-sources', 'attendance.view'],
                'module' => 'attendance',
                'routePattern' => 'admin.attendance.sources.*',
                'keywords' => ['attendance source', 'biometric', 'pos pin', 'driver'],
            ]),
            self::item('leave-types', 'leaveTypes', 'admin.leave.types.index', 'calendar-days', 'leave', 70, [
                'permission' => 'leave.manage-types',
                'permissionsAny' => ['leave.manage-types', 'leave.view'],
                'module' => 'leave',
                'routePattern' => 'admin.leave.types.*',
                'keywords' => ['leave type', 'annual', 'sick', 'unpaid'],
            ]),
            self::item('leave-requests', 'leaveRequests', 'admin.leave.requests.index', 'calendar-range', 'leave', 80, [
                'permission' => 'leave.view',
                'permissionsAny' => ['leave.view', 'leave.request', 'leave.approve'],
                'module' => 'leave',
                'routePattern' => 'admin.leave.requests.*',
                'keywords' => ['leave request', 'time off', 'vacation', 'absence'],
            ]),
        ]));

        $registry->registerSection(new NavigationSection('accounting', 'accountingSection', 60, [
            self::item('chart-of-accounts', 'chartOfAccounts', 'admin.accounting.chart-of-accounts.index', 'book-open', 'accounting', 10, [
                'permission' => 'accounting.view',
                'routePattern' => 'admin.accounting.chart-of-accounts.*',
                'keywords' => ['coa', 'gl', 'ledger', 'accounts'],
            ]),
            self::item('account-mappings', 'accountMappings', 'admin.accounting.account-mappings.index', 'link-2', 'accounting', 20, [
                'permission' => 'accounting.manage-mappings',
                'routePattern' => 'admin.accounting.account-mappings.*',
                'keywords' => ['mapping', 'gl', 'resolver'],
            ]),
            self::item('posting-rules', 'postingRules', 'admin.accounting.posting-rules.index', 'file-spreadsheet', 'accounting', 30, [
                'permission' => 'accounting.manage-posting-rules',
                'routePattern' => 'admin.accounting.posting-rules.*',
                'keywords' => ['posting', 'rules', 'journal', 'auto-post'],
            ]),
            self::item('journal-entries', 'journalEntries', 'admin.accounting.journal-entries.index', 'scroll-text', 'accounting', 40, [
                'permission' => 'accounting.view',
                'routePattern' => 'admin.accounting.journal-entries.*',
                'keywords' => ['journal', 'voucher', 'manual entry', 'gl'],
            ]),
            self::item('cost-centres', 'costCentres', 'admin.accounting.cost-centres.index', 'layers', 'accounting', 50, [
                'permission' => 'accounting.manage-cost-centres',
                'permissionsAny' => ['accounting.manage-cost-centres', 'accounting.view'],
                'module' => 'cost_centres',
                'routePattern' => 'admin.accounting.cost-centres.*',
                'keywords' => ['cost centre', 'department', 'allocation'],
            ]),
            self::item('accounting-settings', 'accountingSettings', 'admin.accounting.settings.index', 'settings-2', 'accounting', 60, [
                'permission' => 'accounting.manage-fiscal-years',
                'permissionsAny' => ['accounting.manage-fiscal-years', 'accounting.view'],
                'routePattern' => 'admin.accounting.settings.*',
                'keywords' => ['fiscal year', 'cutover', 'retained earnings'],
            ]),
            self::item('accounting-modules', 'accountingModules', 'admin.accounting.modules.index', 'boxes', 'accounting', 70, [
                'permission' => 'accounting.manage-modules',
                'routePattern' => 'admin.accounting.modules.*',
                'keywords' => ['modules', 'sub-module', 'enable', 'branch accounting'],
            ]),
            self::item('cost-layers', 'costLayers', 'admin.accounting.cost-layers.create', 'layers', 'accounting', 80, [
                'permission' => 'accounting.manage-fiscal-years',
                'routePattern' => 'admin.accounting.cost-layers.*',
                'keywords' => ['cost layer', 'backfill', 'inventory cost', 'cogs'],
            ]),
            self::item('accounting-reports', 'accountingReports', 'admin.accounting.reports.index', 'bar-chart-3', 'accounting', 90, [
                'permission' => 'accounting.view-reports',
                'routePattern' => 'admin.accounting.reports.*',
                'keywords' => ['trial balance', 'p&l', 'balance sheet', 'ledger'],
            ]),
            self::item('accounting-events', 'accountingEvents', 'admin.accounting.events.index', 'alert-circle', 'accounting', 100, [
                'permission' => 'accounting.view',
                'routePattern' => 'admin.accounting.events.*',
                'keywords' => ['failed', 'retry', 'event', 'posting error'],
            ]),
            self::item('credit-notes', 'creditNotes', 'admin.accounting.credit-notes.index', 'file-spreadsheet', 'accounting', 110, [
                'permission' => 'accounting.view',
                'module' => 'credit_notes',
                'routePattern' => 'admin.accounting.credit-notes.*',
                'keywords' => ['credit note', 'ar', 'customer refund'],
            ]),
            self::item('tax-types', 'taxTypes', 'admin.accounting.tax-types.index', 'scale', 'accounting', 120, [
                'permission' => 'accounting.manage-tax-settings',
                'module' => 'tax',
                'routePattern' => 'admin.accounting.tax-types.*',
                'keywords' => ['vat', 'gst', 'tax rate'],
            ]),
            self::item('bank-accounts', 'bankAccounts', 'admin.accounting.bank-accounts.index', 'landmark', 'accounting', 130, [
                'permission' => 'accounting.manage-bank-accounts',
                'module' => 'bank_reconciliation',
                'routePattern' => 'admin.accounting.bank-accounts.*',
                'keywords' => ['bank', 'gl account'],
            ]),
            self::item('bank-reconciliation', 'bankReconciliation', 'admin.accounting.reconciliation.index', 'clipboard-list', 'accounting', 140, [
                'permission' => 'accounting.reconcile-bank',
                'module' => 'bank_reconciliation',
                'routePattern' => 'admin.accounting.reconciliation.*',
                'keywords' => ['reconcile', 'statement', 'match'],
            ]),
            self::item('currencies', 'currencies', 'admin.accounting.currencies.index', 'coins', 'accounting', 150, [
                'permission' => 'accounting.view',
                'module' => 'multi_currency',
                'routePattern' => 'admin.accounting.currencies.*',
                'keywords' => ['fx', 'exchange rate', 'multi-currency'],
            ]),
            self::item('petty-cash', 'pettyCash', 'admin.accounting.petty-cash.index', 'wallet', 'accounting', 160, [
                'permission' => 'accounting.manage-petty-cash',
                'module' => 'petty_cash',
                'routePattern' => 'admin.accounting.petty-cash.*',
                'keywords' => ['petty cash', 'imprest', 'voucher'],
            ]),
            self::item('cheques', 'cheques', 'admin.accounting.cheques.index', 'receipt', 'accounting', 170, [
                'permission' => 'accounting.manage-cheques',
                'module' => 'cheques',
                'routePattern' => 'admin.accounting.cheques.*',
                'keywords' => ['cheque', 'deposit', 'clearance'],
            ]),
            self::item('fixed-assets', 'fixedAssets', 'admin.accounting.fixed-assets.index', 'package', 'accounting', 180, [
                'permission' => 'accounting.manage-assets',
                'module' => 'fixed_assets',
                'routePattern' => 'admin.accounting.fixed-assets.*',
                'keywords' => ['asset', 'depreciation', 'capital'],
            ]),
        ]));

        $registry->registerSection(new NavigationSection('admin', 'admin', 70, [
            self::item('users', 'users', 'admin.users.index', 'users', 'admin', 10, [
                'permission' => 'users.view',
                'routePattern' => 'admin.users.*',
                'keywords' => ['team', 'staff', 'members'],
            ]),
            self::item('roles', 'roles', 'admin.roles.index', 'shield', 'admin', 20, [
                'permission' => 'roles.view',
                'routePattern' => 'admin.roles.*',
                'keywords' => ['access', 'profiles'],
            ]),
            self::item('permissions', 'permissions', 'admin.permissions.index', 'key-round', 'admin', 30, [
                'permission' => 'permissions.view',
                'routePattern' => 'admin.permissions.*',
                'keywords' => ['capabilities', 'acl'],
            ]),
            self::item('settings', 'settings', 'admin.settings.index', 'settings-2', 'admin', 40, [
                'permission' => 'settings.view',
                'permissionsAny' => [
                    'settings.view',
                    'settings.general.update',
                    'settings.company.update',
                    'settings.notifications.update',
                    'settings.import-export.update',
                ],
                'routePattern' => 'admin.settings.*',
                'keywords' => ['configuration', 'preferences', 'global'],
            ]),
            self::item('help-support', 'helpSupport', 'help-support.index', 'life-buoy', 'admin', 50, [
                'permission' => null,
                'routePattern' => 'help-support.*',
                'keywords' => ['help', 'support', 'docs', 'guides', 'knowledge base'],
            ]),
        ]));
    }

    /**
     * @param  array{
     *     permission?: string|null,
     *     permissionsAny?: list<string>,
     *     keywords?: list<string>,
     *     module?: string|null,
     *     routePattern?: string|null,
     *     activeRoutes?: list<string>|null
     * }  $opts
     */
    private static function item(
        string $id,
        string $titleKey,
        string $route,
        string $icon,
        string $group,
        int $order,
        array $opts = [],
    ): NavigationItem {
        return new NavigationItem(
            id: $id,
            titleKey: $titleKey,
            route: $route,
            icon: $icon,
            group: $group,
            order: $order,
            permission: $opts['permission'] ?? null,
            permissionsAny: $opts['permissionsAny'] ?? [],
            keywords: $opts['keywords'] ?? [],
            module: $opts['module'] ?? null,
            routePattern: $opts['routePattern'] ?? null,
            activeRoutes: $opts['activeRoutes'] ?? null,
        );
    }
}
