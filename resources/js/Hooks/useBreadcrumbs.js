import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const ROUTE_CRUMBS = {
    'Admin/Dashboard': ['home'],
    'Admin/Users/Index': ['home', 'users'],
    'Admin/Users/Create': ['home', 'users', 'create'],
    'Admin/Users/Edit': ['home', 'users', 'edit'],
    'Admin/Roles/Index': ['home', 'roles'],
    'Admin/Roles/Create': ['home', 'roles', 'create'],
    'Admin/Roles/Edit': ['home', 'roles', 'edit'],
    'Admin/Roles/Clone': ['home', 'roles', 'clone'],
    'Admin/Permissions/Index': ['home', 'permissions'],
    'Admin/Permissions/Create': ['home', 'permissions', 'create'],
    'Admin/Permissions/Edit': ['home', 'permissions', 'edit'],
    'Admin/Branches/Index': ['home', 'branches'],
    'Admin/Branches/Create': ['home', 'branches', 'create'],
    'Admin/Branches/Edit': ['home', 'branches', 'edit'],
    'Admin/Warehouses/Index': ['home', 'warehouses'],
    'Admin/Warehouses/Create': ['home', 'warehouses', 'create'],
    'Admin/Warehouses/Edit': ['home', 'warehouses', 'edit'],
    'Admin/Warehouses/Bins/Index': ['home', 'warehouses', 'bins'],
    'Admin/Categories/Index': ['home', 'categories'],
    'Admin/Categories/Create': ['home', 'categories', 'create'],
    'Admin/Categories/Edit': ['home', 'categories', 'edit'],
    'Admin/Brands/Index': ['home', 'brands'],
    'Admin/Brands/Create': ['home', 'brands', 'create'],
    'Admin/Brands/Edit': ['home', 'brands', 'edit'],
    'Admin/Units/Index': ['home', 'units'],
    'Admin/Units/Create': ['home', 'units', 'create'],
    'Admin/Units/Edit': ['home', 'units', 'edit'],
    'Admin/Products/Index': ['home', 'products'],
    'Admin/Products/Create': ['home', 'products', 'create'],
    'Admin/Products/Show': ['home', 'products', 'view'],
    'Admin/Products/Edit': ['home', 'products', 'edit'],
    'Admin/Inventory/Index': ['home', 'inventory'],
    'Admin/Inventory/Adjust': ['home', 'inventory', 'adjust'],
    'Admin/Inventory/Receive': ['home', 'inventory', 'receive'],
    'Admin/Inventory/BinReport': ['home', 'binStock'],
    'Admin/Inventory/BinTransfer': ['home', 'binTransfer'],
    'Admin/Inventory/BranchStockSettings': ['home', 'branchStockSettings'],
    'Admin/Inventory/Quarantine': ['home', 'quarantine'],
    'Admin/StockTransfers/Index': ['home', 'stockTransfers'],
    'Admin/StockTransfers/Create': ['home', 'stockTransfers', 'create'],
    'Admin/StockTransfers/Show': ['home', 'stockTransfers', 'view'],
    'Admin/Suppliers/Index': ['home', 'suppliers'],
    'Admin/Suppliers/Create': ['home', 'suppliers', 'create'],
    'Admin/Suppliers/Edit': ['home', 'suppliers', 'edit'],
    'Admin/Suppliers/Show': ['home', 'suppliers', 'view'],
    'Admin/PurchaseOrders/Index': ['home', 'purchaseOrders'],
    'Admin/PurchaseOrders/Create': ['home', 'purchaseOrders', 'create'],
    'Admin/PurchaseOrders/Show': ['home', 'purchaseOrders', 'view'],
    'Admin/GoodsReceivingNotes/Index': ['home', 'goodsReceiving'],
    'Admin/GoodsReceivingNotes/Show': ['home', 'goodsReceiving', 'view'],
    'Admin/SupplierPriceLists/Index': ['home', 'supplierPriceLists'],
    'Admin/SupplierPriceLists/Create': ['home', 'supplierPriceLists', 'create'],
    'Admin/SupplierPriceLists/Edit': ['home', 'supplierPriceLists', 'edit'],
    'Admin/Procurement/Reports': ['home', 'procurementReports'],
    'Admin/CountSessions/Index': ['home', 'cycleCounts'],
    'Admin/CountSessions/Create': ['home', 'cycleCounts', 'create'],
    'Admin/CountSessions/Show': ['home', 'cycleCounts', 'view'],
    'Admin/CountScheduleRules/Index': ['home', 'countSchedules'],
    'Admin/CountScheduleRules/Create': ['home', 'countSchedules', 'create'],
    'Admin/CountScheduleRules/Edit': ['home', 'countSchedules', 'edit'],
    'Admin/Customers/Index': ['home', 'customers'],
    'Admin/Customers/Create': ['home', 'customers', 'create'],
    'Admin/Customers/Edit': ['home', 'customers', 'edit'],
    'Admin/Customers/Show': ['home', 'customers', 'view'],
    'Admin/CustomerGroups/Index': ['home', 'customerGroups'],
    'Admin/CustomerGroups/Create': ['home', 'customerGroups', 'create'],
    'Admin/CustomerGroups/Edit': ['home', 'customerGroups', 'edit'],
    'Admin/ArAging/Index': ['home', 'arAging'],
    'Admin/Sales/Index': ['home', 'sales'],
    'Admin/Sales/Show': ['home', 'sales', 'view'],
    'Admin/Settings/Index': ['home', 'settings'],
    'Admin/Settings/Edit': ['home', 'settings', 'edit'],
};

const CRUMB_HREFS = {
    home: 'admin.dashboard',
    users: 'admin.users.index',
    roles: 'admin.roles.index',
    permissions: 'admin.permissions.index',
    branches: 'admin.branches.index',
    warehouses: 'admin.warehouses.index',
    categories: 'admin.categories.index',
    brands: 'admin.brands.index',
    units: 'admin.units.index',
    products: 'admin.products.index',
    inventory: 'admin.inventory.index',
    stockTransfers: 'admin.stock-transfers.index',
    suppliers: 'admin.suppliers.index',
    purchaseOrders: 'admin.purchase-orders.index',
    goodsReceiving: 'admin.goods-receiving-notes.index',
    supplierPriceLists: 'admin.supplier-price-lists.index',
    procurementReports: 'admin.procurement.reports',
    binStock: 'admin.inventory.bin-report',
    binTransfer: 'admin.inventory.bin-transfer.form',
    branchStockSettings: 'admin.inventory.branch-stock-settings',
    quarantine: 'admin.inventory.quarantine',
    cycleCounts: 'admin.count-sessions.index',
    countSchedules: 'admin.count-schedule-rules.index',
    customers: 'admin.customers.index',
    customerGroups: 'admin.customer-groups.index',
    arAging: 'admin.ar-aging.index',
    sales: 'admin.sales.index',
    settings: 'admin.settings.index',
};

export function useBreadcrumbs() {
    const { component, props } = usePage();
    const { t } = useTranslation();

    return useMemo(() => {
        if (props.breadcrumbs?.length) {
            return props.breadcrumbs;
        }

        const keys = ROUTE_CRUMBS[component] ?? ['home'];

        return keys.map((key, index) => ({
            label: t(`breadcrumbs.${key}`),
            href:
                index < keys.length - 1 && CRUMB_HREFS[key]
                    ? route(CRUMB_HREFS[key])
                    : undefined,
        }));
    }, [component, props.breadcrumbs, t]);
}
