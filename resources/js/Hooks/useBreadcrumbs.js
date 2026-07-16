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
    'Admin/Loyalty/Programs/Index': ['home', 'loyaltyPrograms'],
    'Admin/Loyalty/Programs/Create': ['home', 'loyaltyPrograms', 'create'],
    'Admin/Loyalty/Programs/Edit': ['home', 'loyaltyPrograms', 'edit'],
    'Admin/Loyalty/Programs/Show': ['home', 'loyaltyPrograms', 'view'],
    'Admin/Loyalty/Transactions/Index': ['home', 'loyaltyPrograms', 'loyaltyTransactions'],
    'Admin/Loyalty/Reports': ['home', 'loyaltyReports'],
    'Admin/Sales/Index': ['home', 'sales'],
    'Admin/Sales/Show': ['home', 'sales', 'view'],
    'Admin/Settings/Index': ['home', 'settings'],
    'Admin/Settings/Edit': ['home', 'settings', 'edit'],
    'Admin/Accounting/ChartOfAccounts/Index': ['home', 'chartOfAccounts'],
    'Admin/Accounting/AccountMappings/Index': ['home', 'accountMappings'],
    'Admin/Accounting/PostingRules/Index': ['home', 'postingRules'],
    'Admin/Accounting/PostingRules/Edit': ['home', 'postingRules', 'edit'],
    'Admin/Accounting/PostingRules/Create': ['home', 'postingRules', 'create'],
    'Admin/Accounting/JournalEntries/Index': ['home', 'journalEntries'],
    'Admin/Accounting/JournalEntries/Create': ['home', 'journalEntries', 'create'],
    'Admin/Accounting/JournalEntries/Show': ['home', 'journalEntries', 'view'],
    'Admin/Accounting/CostCentres/Index': ['home', 'costCentres'],
    'Admin/Accounting/Settings/Index': ['home', 'accountingSettings'],
    'Admin/Accounting/Reports/Index': ['home', 'accountingReports'],
    'Admin/Accounting/Reports/Show': ['home', 'accountingReports', 'view'],
    'Admin/Accounting/Events/Index': ['home', 'accountingEvents'],
    'Admin/Accounting/CreditNotes/Index': ['home', 'creditNotes'],
    'Admin/Accounting/CreditNotes/Create': ['home', 'creditNotes', 'create'],
    'Admin/Accounting/TaxTypes/Index': ['home', 'taxTypes'],
    'Admin/Accounting/BankAccounts/Index': ['home', 'bankAccounts'],
    'Admin/Accounting/Reconciliation/Index': ['home', 'bankReconciliation'],
    'Admin/Accounting/Currencies/Index': ['home', 'currencies'],
    'Admin/Accounting/PettyCash/Index': ['home', 'pettyCash'],
    'Admin/Accounting/Cheques/Index': ['home', 'cheques'],
    'Admin/Accounting/FixedAssets/Index': ['home', 'fixedAssets'],
    'Admin/Hr/Employees/Index': ['home', 'hrEmployees'],
    'Admin/Hr/Employees/Create': ['home', 'hrEmployees', 'create'],
    'Admin/Hr/Employees/Edit': ['home', 'hrEmployees', 'edit'],
    'Admin/Hr/Employees/Show': ['home', 'hrEmployees', 'view'],
    'Admin/Hr/Departments/Index': ['home', 'hrDepartments'],
    'Admin/Hr/Designations/Index': ['home', 'hrDesignations'],
    'Admin/Hr/Grades/Index': ['home', 'hrGrades'],
    'Admin/Hr/HolidayCalendars/Index': ['home', 'holidayCalendars'],
    'Admin/Hr/HolidayCalendars/Show': ['home', 'holidayCalendars', 'view'],
    'Admin/Expenses/Index': ['home', 'expenses'],
    'Admin/Expenses/Create': ['home', 'expenses', 'create'],
    'Admin/Expenses/Show': ['home', 'expenses', 'view'],
    'Admin/ExpenseCategories/Index': ['home', 'expenseCategories'],
    'Admin/RecurringExpenses/Index': ['home', 'recurringExpenses'],
    'Admin/RecurringExpenses/Create': ['home', 'recurringExpenses', 'create'],
    'Admin/Overtime/Policies/Index': ['home', 'overtimePolicies'],
    'Admin/Overtime/Records/Index': ['home', 'overtimeRecords'],
    'Admin/Leave/Types/Index': ['home', 'leaveTypes'],
    'Admin/Leave/Requests/Index': ['home', 'leaveRequests'],
    'Admin/Leave/Requests/Create': ['home', 'leaveRequests', 'create'],
    'Admin/Attendance/Records/Index': ['home', 'attendanceRecords'],
    'Admin/Attendance/Records/Create': ['home', 'attendanceRecords', 'create'],
    'Admin/Attendance/Sources/Index': ['home', 'attendanceSources'],
    'Admin/Payroll/Runs/Index': ['home', 'payrollRuns'],
    'Admin/Payroll/Runs/Show': ['home', 'payrollRuns', 'view'],
    'Admin/Payroll/PayComponents/Index': ['home', 'payComponents'],
    'Admin/Payroll/TaxSlabs/Index': ['home', 'taxSlabs'],
    'Admin/Payroll/StatutorySchemes/Index': ['home', 'statutorySchemes'],
    'Admin/SelfService/Payslips/Index': ['home', 'selfServicePayslips'],
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
    loyaltyPrograms: 'admin.loyalty.programs.index',
    loyaltyTransactions: 'admin.loyalty.transactions.index',
    loyaltyReports: 'admin.loyalty.reports.index',
    sales: 'admin.sales.index',
    settings: 'admin.settings.index',
    chartOfAccounts: 'admin.accounting.chart-of-accounts.index',
    accountMappings: 'admin.accounting.account-mappings.index',
    postingRules: 'admin.accounting.posting-rules.index',
    journalEntries: 'admin.accounting.journal-entries.index',
    costCentres: 'admin.accounting.cost-centres.index',
    accountingSettings: 'admin.accounting.settings.index',
    accountingReports: 'admin.accounting.reports.index',
    accountingEvents: 'admin.accounting.events.index',
    creditNotes: 'admin.accounting.credit-notes.index',
    taxTypes: 'admin.accounting.tax-types.index',
    bankAccounts: 'admin.accounting.bank-accounts.index',
    bankReconciliation: 'admin.accounting.reconciliation.index',
    currencies: 'admin.accounting.currencies.index',
    pettyCash: 'admin.accounting.petty-cash.index',
    cheques: 'admin.accounting.cheques.index',
    fixedAssets: 'admin.accounting.fixed-assets.index',
    hrEmployees: 'admin.hr.employees.index',
    hrDepartments: 'admin.hr.departments.index',
    hrDesignations: 'admin.hr.designations.index',
    hrGrades: 'admin.hr.grades.index',
    holidayCalendars: 'admin.hr.holiday-calendars.index',
    expenses: 'admin.expenses.expenses.index',
    expenseCategories: 'admin.expenses.expense-categories.index',
    recurringExpenses: 'admin.expenses.recurring-expenses.index',
    overtimePolicies: 'admin.overtime.policies.index',
    overtimeRecords: 'admin.overtime.records.index',
    leaveTypes: 'admin.leave.types.index',
    leaveRequests: 'admin.leave.requests.index',
    attendanceRecords: 'admin.attendance.records.index',
    attendanceSources: 'admin.attendance.sources.index',
    payrollRuns: 'admin.payroll.runs.index',
    payComponents: 'admin.payroll.pay-components.index',
    taxSlabs: 'admin.payroll.tax-slabs.index',
    statutorySchemes: 'admin.payroll.statutory-schemes.index',
    selfServicePayslips: 'admin.self-service.payslips.index',
};

export function useBreadcrumbs() {
    const { component, props } = usePage();
    const { t } = useTranslation();

    return useMemo(() => {
        if (props.breadcrumbs?.length) {
            return props.breadcrumbs;
        }

        if (component === 'Admin/Loyalty/Programs/Show' && props.program?.name) {
            return [
                { label: t('breadcrumbs.home'), href: route(CRUMB_HREFS.home) },
                { label: t('breadcrumbs.loyaltyPrograms'), href: route(CRUMB_HREFS.loyaltyPrograms) },
                { label: props.program.name },
            ];
        }

        if (component === 'Admin/Loyalty/Programs/Edit' && props.program?.name) {
            return [
                { label: t('breadcrumbs.home'), href: route(CRUMB_HREFS.home) },
                { label: t('breadcrumbs.loyaltyPrograms'), href: route(CRUMB_HREFS.loyaltyPrograms) },
                {
                    label: props.program.name,
                    href: route('admin.loyalty.programs.show', props.program.id),
                },
                { label: t('breadcrumbs.edit') },
            ];
        }

        if (component === 'Admin/Accounting/JournalEntries/Show' && props.journalEntry?.journal_number) {
            return [
                { label: t('breadcrumbs.home'), href: route(CRUMB_HREFS.home) },
                { label: t('breadcrumbs.journalEntries'), href: route(CRUMB_HREFS.journalEntries) },
                { label: props.journalEntry.journal_number },
            ];
        }

        if (component === 'Admin/Accounting/PostingRules/Edit' && props.ruleSet?.name) {
            return [
                { label: t('breadcrumbs.home'), href: route(CRUMB_HREFS.home) },
                { label: t('breadcrumbs.postingRules'), href: route(CRUMB_HREFS.postingRules) },
                { label: props.ruleSet.name },
            ];
        }

        if (component === 'Admin/Accounting/PostingRules/Create' && props.source?.code) {
            return [
                { label: t('breadcrumbs.home'), href: route(CRUMB_HREFS.home) },
                { label: t('breadcrumbs.postingRules'), href: route(CRUMB_HREFS.postingRules) },
                { label: t('pages.accounting.postingRules.createTitle') },
            ];
        }

        if (component === 'Admin/Accounting/Reports/Show' && props.title) {
            return [
                { label: t('breadcrumbs.home'), href: route(CRUMB_HREFS.home) },
                { label: t('breadcrumbs.accountingReports'), href: route(CRUMB_HREFS.accountingReports) },
                { label: props.title },
            ];
        }

        if (component === 'Admin/Hr/HolidayCalendars/Show' && props.calendar?.name) {
            return [
                { label: t('breadcrumbs.home'), href: route(CRUMB_HREFS.home) },
                { label: t('breadcrumbs.holidayCalendars'), href: route(CRUMB_HREFS.holidayCalendars) },
                { label: props.calendar.name },
            ];
        }

        if (component === 'Admin/Hr/Employees/Show' && props.employee?.name) {
            return [
                { label: t('breadcrumbs.home'), href: route(CRUMB_HREFS.home) },
                { label: t('breadcrumbs.hrEmployees'), href: route(CRUMB_HREFS.hrEmployees) },
                { label: props.employee.name },
            ];
        }

        const keys = ROUTE_CRUMBS[component] ?? ['home'];

        return keys.map((key, index) => ({
            label: t(`breadcrumbs.${key}`),
            href:
                index < keys.length - 1 && CRUMB_HREFS[key]
                    ? route(CRUMB_HREFS[key])
                    : undefined,
        }));
    }, [component, props.breadcrumbs, props.program, props.journalEntry, props.ruleSet, props.title, props.calendar, props.employee, t]);
}
