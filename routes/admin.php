<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccountingEventController;
use App\Http\Controllers\Admin\AccountingImportController;
use App\Http\Controllers\Admin\AccountingModulesController;
use App\Http\Controllers\Admin\AccountingReportController;
use App\Http\Controllers\Admin\AccountingSettingsController;
use App\Http\Controllers\Admin\AccountMappingController;
use App\Http\Controllers\Admin\ArAgingController;
use App\Http\Controllers\Admin\Attendance\AttendanceRecordController;
use App\Http\Controllers\Admin\Attendance\AttendanceSourceController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\BankReconciliationController;
use App\Http\Controllers\Admin\BranchContextController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CatalogBulkController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChartOfAccountController;
use App\Http\Controllers\Admin\ChequeController;
use App\Http\Controllers\Admin\CostCentreController;
use App\Http\Controllers\Admin\CostLayerController;
use App\Http\Controllers\Admin\CountScheduleRuleController;
use App\Http\Controllers\Admin\CountSessionController;
use App\Http\Controllers\Admin\CreditNoteController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\CustomerWalletController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Expense\ExpenseCategoryController;
use App\Http\Controllers\Admin\Expense\ExpenseController;
use App\Http\Controllers\Admin\Expense\RecurringExpenseScheduleController;
use App\Http\Controllers\Admin\FixedAssetController;
use App\Http\Controllers\Admin\GoodsReceivingNoteController;
use App\Http\Controllers\Admin\Hr\ApprovalDelegationController;
use App\Http\Controllers\Admin\Hr\DepartmentController;
use App\Http\Controllers\Admin\Hr\DesignationController;
use App\Http\Controllers\Admin\Hr\EmployeeController;
use App\Http\Controllers\Admin\Hr\GradeController;
use App\Http\Controllers\Admin\Hr\HolidayCalendarController;
use App\Http\Controllers\Admin\Hr\HrEmploymentTypeController;
use App\Http\Controllers\Admin\Hr\HrEntitySettingsController;
use App\Http\Controllers\Admin\Hr\HrModulesController;
use App\Http\Controllers\Admin\Hr\OrgChartController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\JournalEntryController;
use App\Http\Controllers\Admin\LandedCostController;
use App\Http\Controllers\Admin\Leave\LeaveEncashmentController;
use App\Http\Controllers\Admin\Leave\LeavePolicyController;
use App\Http\Controllers\Admin\Leave\LeaveRequestController;
use App\Http\Controllers\Admin\Leave\LeaveTypeController;
use App\Http\Controllers\Admin\Leave\LeaveYearEndRunController;
use App\Http\Controllers\Admin\LoyaltyProgramConfigController;
use App\Http\Controllers\Admin\LoyaltyProgramController;
use App\Http\Controllers\Admin\LoyaltyReportController;
use App\Http\Controllers\Admin\LoyaltyTransactionController;
use App\Http\Controllers\Admin\Overtime\OvertimePolicyController;
use App\Http\Controllers\Admin\Overtime\OvertimeRecordController;
use App\Http\Controllers\Admin\Overtime\ToilCashClaimController;
use App\Http\Controllers\Admin\Payroll\PayComponentController;
use App\Http\Controllers\Admin\Payroll\PayrollRunController;
use App\Http\Controllers\Admin\Payroll\PayslipController;
use App\Http\Controllers\Admin\Payroll\StatutorySchemeController;
use App\Http\Controllers\Admin\Payroll\TaxSlabController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PettyCashController;
use App\Http\Controllers\Admin\PoMatchController;
use App\Http\Controllers\Admin\PostingRuleController;
use App\Http\Controllers\Admin\ProcurementReportController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SelfService\EmployeeSelfServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SupplierAttachmentController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierInvoiceController;
use App\Http\Controllers\Admin\SupplierPaymentController;
use App\Http\Controllers\Admin\SupplierPriceListController;
use App\Http\Controllers\Admin\TaxTypeController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseBinController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\CheckoutPageController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'branch.context', 'pos.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('checkout/{cartId}', [CheckoutPageController::class, 'show'])->name('checkout.show');
    });

Route::middleware(['auth', 'admin', 'branch.context'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::put('/branch-context', [BranchContextController::class, 'update'])
            ->name('branch-context.update');

        Route::get('branches/suggest-code', [BranchController::class, 'suggestCode'])
            ->name('branches.suggest-code');
        Route::resource('branches', BranchController::class)->except(['show']);
        Route::get('warehouses/suggest-code', [WarehouseController::class, 'suggestCode'])
            ->name('warehouses.suggest-code');
        Route::resource('warehouses', WarehouseController::class)->except(['show', 'destroy']);
        Route::patch('warehouses/{warehouse}/deactivate', [WarehouseController::class, 'deactivate'])
            ->name('warehouses.deactivate');
        Route::get('warehouses/{warehouse}/bins', [WarehouseBinController::class, 'index'])
            ->name('warehouses.bins.index');
        Route::post('warehouses/{warehouse}/zones', [WarehouseBinController::class, 'storeZone'])
            ->name('warehouses.zones.store');
        Route::put('warehouses/{warehouse}/zones/{zone}', [WarehouseBinController::class, 'updateZone'])
            ->name('warehouses.zones.update');
        Route::post('warehouses/{warehouse}/bins', [WarehouseBinController::class, 'storeBin'])
            ->name('warehouses.bins.store');
        Route::put('warehouses/{warehouse}/bins/{bin}', [WarehouseBinController::class, 'updateBin'])
            ->name('warehouses.bins.update');
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('brands', BrandController::class)->except(['show']);
        Route::resource('units', UnitController::class)->except(['show']);
        Route::post('catalog/bulk/delete', [CatalogBulkController::class, 'destroy'])
            ->name('catalog.bulk.delete');
        Route::post('catalog/bulk/deactivate', [CatalogBulkController::class, 'deactivate'])
            ->name('catalog.bulk.deactivate');
        Route::get('product-variants/search', [ProductController::class, 'searchVariants'])
            ->name('product-variants.search');
        Route::resource('products', ProductController::class);
        Route::post('products/{product}/images', [ProductImageController::class, 'sync'])
            ->name('products.images.sync');

        Route::middleware(['hr-module:hr'])->prefix('hr')->name('hr.')->group(function () {
            Route::resource('employees', EmployeeController::class)->except(['destroy']);
            Route::post('employees/{employee}/terminate', [EmployeeController::class, 'terminate'])
                ->name('employees.terminate');
            Route::post('employees/{employee}/reactivate', [EmployeeController::class, 'reactivate'])
                ->name('employees.reactivate');
            Route::delete('employees/{employee}/images/{image}', [EmployeeController::class, 'destroyImage'])
                ->name('employees.images.destroy');
            Route::resource('departments', DepartmentController::class)->except(['show', 'destroy']);
            Route::resource('designations', DesignationController::class)->except(['show', 'destroy']);
            Route::resource('grades', GradeController::class)->except(['show', 'destroy']);
            Route::resource('employment-types', HrEmploymentTypeController::class)->only(['index', 'store', 'update']);
            Route::get('settings', [HrEntitySettingsController::class, 'index'])->name('settings.index');
            Route::put('settings', [HrEntitySettingsController::class, 'update'])->name('settings.update');
            Route::get('modules', [HrModulesController::class, 'index'])->name('modules.index');
            Route::put('modules', [HrModulesController::class, 'update'])->name('modules.update');
            Route::get('org-chart', [OrgChartController::class, 'index'])->name('org-chart.index');
            Route::resource('delegations', ApprovalDelegationController::class)->only(['index', 'store', 'update']);
        });

        Route::middleware(['hr-module:holiday_calendar'])->prefix('hr')->name('hr.')->group(function () {
            Route::resource('holiday-calendars', HolidayCalendarController::class)->except(['destroy']);
            Route::post('holiday-calendars/{holiday_calendar}/dates', [HolidayCalendarController::class, 'storeDate'])->name('holiday-calendars.dates.store');
            Route::delete('holiday-calendars/{holiday_calendar}/dates/{holiday_date}', [HolidayCalendarController::class, 'destroyDate'])->name('holiday-calendars.dates.destroy');
            Route::post('holiday-calendars/{holiday_calendar}/assignments', [HolidayCalendarController::class, 'storeAssignment'])->name('holiday-calendars.assignments.store');
            Route::delete('holiday-calendars/{holiday_calendar}/assignments/{assignment}', [HolidayCalendarController::class, 'destroyAssignment'])->name('holiday-calendars.assignments.destroy');
        });

        Route::middleware(['hr-module:expenses'])->prefix('expenses')->name('expenses.')->group(function () {
            Route::resource('expense-categories', ExpenseCategoryController::class)->except(['show', 'destroy']);
            Route::resource('expenses', ExpenseController::class)->except(['destroy', 'edit', 'update']);
            Route::resource('recurring-expenses', RecurringExpenseScheduleController::class)->only(['index', 'create', 'store']);
            Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
            Route::post('expenses/{expense}/attachments', [ExpenseController::class, 'attachReceipt'])->name('expenses.attachments');
        });

        Route::middleware(['hr-module:attendance'])->prefix('attendance')->name('attendance.')->group(function () {
            Route::get('sources', [AttendanceSourceController::class, 'index'])->name('sources.index');
            Route::get('records', [AttendanceRecordController::class, 'index'])->name('records.index');
            Route::get('records/manual', [AttendanceRecordController::class, 'create'])->name('records.create');
            Route::post('records/manual', [AttendanceRecordController::class, 'store'])->name('records.store');
        });

        Route::middleware(['hr-module:leave'])->prefix('leave')->name('leave.')->group(function () {
            Route::get('types', [LeaveTypeController::class, 'index'])->name('types.index');
            Route::post('types', [LeaveTypeController::class, 'store'])->name('types.store');
            Route::put('types/{leave_type}', [LeaveTypeController::class, 'update'])->name('types.update');
            Route::get('policies', [LeavePolicyController::class, 'index'])->name('policies.index');
            Route::post('policies', [LeavePolicyController::class, 'store'])->name('policies.store');
            Route::put('policies/{policy}', [LeavePolicyController::class, 'update'])->name('policies.update');
            Route::get('requests', [LeaveRequestController::class, 'index'])->name('requests.index');
            Route::get('requests/create', [LeaveRequestController::class, 'create'])->name('requests.create');
            Route::post('requests', [LeaveRequestController::class, 'store'])->name('requests.store');
            Route::post('requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('requests.approve');
            Route::post('requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('requests.reject');
            Route::post('requests/{leaveRequest}/reschedule', [LeaveRequestController::class, 'reschedule'])->name('requests.reschedule');
            Route::get('encashments', [LeaveEncashmentController::class, 'index'])->name('encashments.index');
            Route::get('encashments/create', [LeaveEncashmentController::class, 'create'])->name('encashments.create');
            Route::post('encashments', [LeaveEncashmentController::class, 'store'])->name('encashments.store');
            Route::post('encashments/{leaveEncashment}/approve', [LeaveEncashmentController::class, 'approve'])->name('encashments.approve');
            Route::post('encashments/{leaveEncashment}/reject', [LeaveEncashmentController::class, 'reject'])->name('encashments.reject');
            Route::post('encashments/{leaveEncashment}/cancel', [LeaveEncashmentController::class, 'cancel'])->name('encashments.cancel');
            Route::get('year-end-runs', [LeaveYearEndRunController::class, 'index'])->name('year-end-runs.index');
        });

        Route::middleware(['hr-module:overtime'])->prefix('overtime')->name('overtime.')->group(function () {
            Route::get('policies', [OvertimePolicyController::class, 'index'])->name('policies.index');
            Route::post('policies', [OvertimePolicyController::class, 'store'])->name('policies.store');
            Route::put('policies/{overtime_policy}', [OvertimePolicyController::class, 'update'])->name('policies.update');
            Route::get('records', [OvertimeRecordController::class, 'index'])->name('records.index');
            Route::post('records/{overtimeRecord}/approve', [OvertimeRecordController::class, 'approve'])->name('records.approve');
            Route::post('records/{overtimeRecord}/reject', [OvertimeRecordController::class, 'reject'])->name('records.reject');
            Route::get('toil-claims', [ToilCashClaimController::class, 'index'])->name('toil-claims.index');
            Route::get('toil-claims/create', [ToilCashClaimController::class, 'create'])->name('toil-claims.create');
            Route::post('toil-claims', [ToilCashClaimController::class, 'store'])->name('toil-claims.store');
            Route::post('toil-claims/{toilClaim}/approve', [ToilCashClaimController::class, 'approve'])->name('toil-claims.approve');
            Route::post('toil-claims/{toilClaim}/reject', [ToilCashClaimController::class, 'reject'])->name('toil-claims.reject');
            Route::post('toil-claims/{toilClaim}/cancel', [ToilCashClaimController::class, 'cancel'])->name('toil-claims.cancel');
        });

        Route::middleware(['hr-module:payroll'])->prefix('payroll')->name('payroll.')->group(function () {
            Route::get('pay-components', [PayComponentController::class, 'index'])->name('pay-components.index');
            Route::post('pay-components', [PayComponentController::class, 'store'])->name('pay-components.store');
            Route::put('pay-components/{pay_component}', [PayComponentController::class, 'update'])->name('pay-components.update');
            Route::delete('pay-components/{pay_component}', [PayComponentController::class, 'destroy'])->name('pay-components.destroy');

            Route::get('tax-slabs', [TaxSlabController::class, 'index'])->name('tax-slabs.index');
            Route::post('tax-slabs', [TaxSlabController::class, 'store'])->name('tax-slabs.store');
            Route::put('tax-slabs/{tax_slab}', [TaxSlabController::class, 'update'])->name('tax-slabs.update');
            Route::delete('tax-slabs/{tax_slab}', [TaxSlabController::class, 'destroy'])->name('tax-slabs.destroy');

            Route::get('statutory-schemes', [StatutorySchemeController::class, 'index'])->name('statutory-schemes.index');
            Route::post('statutory-schemes', [StatutorySchemeController::class, 'store'])->name('statutory-schemes.store');
            Route::put('statutory-schemes/{statutory_scheme}', [StatutorySchemeController::class, 'update'])->name('statutory-schemes.update');

            Route::get('runs', [PayrollRunController::class, 'index'])->name('runs.index');
            Route::post('runs', [PayrollRunController::class, 'store'])->name('runs.store');
            Route::get('runs/{payroll_run}', [PayrollRunController::class, 'show'])->name('runs.show');
            Route::post('runs/{payroll_run}/calculate', [PayrollRunController::class, 'calculate'])->name('runs.calculate');
            Route::post('runs/{payroll_run}/submit', [PayrollRunController::class, 'submit'])->name('runs.submit');
            Route::post('runs/{payroll_run}/approve', [PayrollRunController::class, 'approve'])->name('runs.approve');
            Route::post('runs/{payroll_run}/post', [PayrollRunController::class, 'post'])->name('runs.post');
            Route::post('runs/{payroll_run}/reverse', [PayrollRunController::class, 'reverse'])->name('runs.reverse');
            Route::post('runs/{payroll_run}/payslips/email', [PayslipController::class, 'bulkEmail'])->name('runs.payslips.email');
            Route::post('items/{payroll_item}/payslip/generate', [PayslipController::class, 'generate'])->name('items.payslip.generate');
            Route::get('items/{payroll_item}/payslip', [PayslipController::class, 'download'])->name('items.payslip.download');
        });

        Route::middleware(['hr-module:employee_self_service'])->prefix('self-service')->name('self-service.')->group(function () {
            Route::get('payslips', [EmployeeSelfServiceController::class, 'index'])->name('payslips.index');
            Route::get('payslips/{payslip}/download', [EmployeeSelfServiceController::class, 'download'])->name('payslips.download');
        });

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/adjust', [InventoryController::class, 'adjustForm'])->name('inventory.adjust');
        Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust.store');
        Route::get('inventory/receive', [InventoryController::class, 'receiveForm'])->name('inventory.receive');
        Route::post('inventory/receive', [InventoryController::class, 'receive'])->name('inventory.receive.store');
        Route::get('inventory/bin-report', [InventoryController::class, 'binReport'])->name('inventory.bin-report');
        Route::get('inventory/bin-transfer', [InventoryController::class, 'binTransferForm'])->name('inventory.bin-transfer.form');
        Route::post('inventory/bin-transfer', [WarehouseBinController::class, 'transfer'])->name('inventory.bin-transfer');
        Route::get('inventory/branch-stock-settings', [InventoryController::class, 'branchStockSettings'])
            ->name('inventory.branch-stock-settings');
        Route::put('inventory/branch-stock-settings', [InventoryController::class, 'updateBranchStockSettings'])
            ->name('inventory.branch-stock-settings.update');
        Route::get('inventory/quarantine', [InventoryController::class, 'quarantineIndex'])->name('inventory.quarantine');
        Route::post('inventory/quarantine/release', [InventoryController::class, 'releaseQuarantine'])
            ->name('inventory.quarantine.release');
        Route::post('inventory/quarantine/scrap', [InventoryController::class, 'scrapQuarantine'])
            ->name('inventory.quarantine.scrap');

        Route::resource('count-sessions', CountSessionController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('count-sessions/{count_session}/start', [CountSessionController::class, 'start'])
            ->name('count-sessions.start');
        Route::post('count-sessions/{count_session}/submit-counts', [CountSessionController::class, 'submitCounts'])
            ->name('count-sessions.submit-counts');
        Route::post('count-sessions/{count_session}/approve', [CountSessionController::class, 'approve'])
            ->name('count-sessions.approve');
        Route::post('count-sessions/{count_session}/post', [CountSessionController::class, 'post'])
            ->name('count-sessions.post');

        Route::resource('count-schedule-rules', CountScheduleRuleController::class)
            ->except(['show']);

        Route::resource('stock-transfers', StockTransferController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::resource('suppliers', SupplierController::class);
        Route::post('suppliers/{supplier}/deactivate', [SupplierController::class, 'deactivate'])
            ->name('suppliers.deactivate');
        Route::get('suppliers/{supplier}/statement/pdf', [SupplierController::class, 'statementPdf'])
            ->name('suppliers.statement.pdf');
        Route::post('suppliers/{supplier}/send-statement', [SupplierController::class, 'sendStatement'])
            ->name('suppliers.send-statement');
        Route::post('suppliers/{supplier}/attachments', [SupplierAttachmentController::class, 'store'])
            ->name('suppliers.attachments.store');
        Route::delete('suppliers/{supplier}/attachments/{attachment}', [SupplierAttachmentController::class, 'destroy'])
            ->name('suppliers.attachments.destroy');
        Route::get('suppliers/{supplier}/attachments/{attachment}/download', [SupplierAttachmentController::class, 'download'])
            ->name('suppliers.attachments.download');
        Route::get('suppliers/{supplier}/attachments/{attachment}/preview', [SupplierAttachmentController::class, 'preview'])
            ->name('suppliers.attachments.preview');

        Route::resource('supplier-price-lists', SupplierPriceListController::class)->except(['show']);

        Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('purchase-orders/sales/search', [PurchaseOrderController::class, 'searchSales'])
            ->name('purchase-orders.sales.search');
        Route::post('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit'])
            ->name('purchase-orders.submit');
        Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])
            ->name('purchase-orders.approve');
        Route::post('purchase-orders/{purchase_order}/reject', [PurchaseOrderController::class, 'reject'])
            ->name('purchase-orders.reject');
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('purchase-orders.cancel');
        Route::post('purchase-orders/{purchase_order}/close', [PurchaseOrderController::class, 'close'])
            ->name('purchase-orders.close');
        Route::get('purchase-orders/{purchase_order}/pdf', [PurchaseOrderController::class, 'pdf'])
            ->name('purchase-orders.pdf');
        Route::post('purchase-orders/{purchase_order}/email', [PurchaseOrderController::class, 'email'])
            ->name('purchase-orders.email');
        Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])
            ->name('purchase-orders.receive');

        Route::resource('goods-receiving-notes', GoodsReceivingNoteController::class)
            ->only(['index', 'show']);
        Route::post('goods-receiving-notes/{goods_receiving_note}/invoices', [SupplierInvoiceController::class, 'store'])
            ->name('goods-receiving-notes.invoices.store');
        Route::post('goods-receiving-notes/{goods_receiving_note}/returns', [PurchaseReturnController::class, 'store'])
            ->name('goods-receiving-notes.returns.store');
        Route::post('goods-receiving-notes/{goods_receiving_note}/landed-costs', [LandedCostController::class, 'store'])
            ->name('goods-receiving-notes.landed-costs.store');
        Route::delete('goods-receiving-notes/{goods_receiving_note}/landed-costs/{landed_cost_entry}', [LandedCostController::class, 'destroy'])
            ->name('goods-receiving-notes.landed-costs.destroy');

        Route::post('supplier-invoices/{supplier_invoice}/approve', [SupplierInvoiceController::class, 'approve'])
            ->name('supplier-invoices.approve');
        Route::get('supplier-invoices/{supplier_invoice}/pdf', [SupplierInvoiceController::class, 'pdf'])
            ->name('supplier-invoices.pdf');
        Route::post('supplier-payments', [SupplierPaymentController::class, 'store'])
            ->name('supplier-payments.store');
        Route::post('po-match-results/{po_match_result}/resolve', [PoMatchController::class, 'resolve'])
            ->name('po-match-results.resolve');

        Route::post('purchase-returns/{purchase_return}/approve', [PurchaseReturnController::class, 'approve'])
            ->name('purchase-returns.approve');
        Route::post('purchase-returns/{purchase_return}/dispatch', [PurchaseReturnController::class, 'dispatch'])
            ->name('purchase-returns.dispatch');
        Route::post('purchase-returns/{purchase_return}/debit-note', [PurchaseReturnController::class, 'issueDebitNote'])
            ->name('purchase-returns.debit-note');
        Route::post('purchase-returns/{purchase_return}/acknowledge', [PurchaseReturnController::class, 'acknowledge'])
            ->name('purchase-returns.acknowledge');
        Route::post('purchase-returns/{purchase_return}/close', [PurchaseReturnController::class, 'close'])
            ->name('purchase-returns.close');
        Route::get('debit-notes/{debit_note}/pdf', [PurchaseReturnController::class, 'debitNotePdf'])
            ->name('debit-notes.pdf');

        Route::get('procurement/reports', [ProcurementReportController::class, 'index'])
            ->name('procurement.reports');

        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/wallet/top-up', [CustomerWalletController::class, 'topUp'])
            ->name('customers.wallet.top-up');
        Route::post('customers/{customer}/send-statement', [CustomerController::class, 'sendStatement'])
            ->name('customers.send-statement');
        Route::resource('customer-groups', CustomerGroupController::class)->except(['show']);
        Route::get('ar-aging', [ArAgingController::class, 'index'])->name('ar-aging.index');
        Route::get('ar-aging/export', [ArAgingController::class, 'export'])->name('ar-aging.export');

        Route::prefix('loyalty')->name('loyalty.')->group(function () {
            Route::resource('programs', LoyaltyProgramController::class)->except(['destroy']);
            Route::post('programs/{program}/activate', [LoyaltyProgramController::class, 'activate'])
                ->name('programs.activate');
            Route::post('programs/{program}/deactivate', [LoyaltyProgramController::class, 'deactivate'])
                ->name('programs.deactivate');

            Route::post('programs/{program}/rules', [LoyaltyProgramConfigController::class, 'storeRule'])
                ->name('programs.rules.store');
            Route::put('programs/{program}/rules/{rule}', [LoyaltyProgramConfigController::class, 'updateRule'])
                ->name('programs.rules.update');
            Route::delete('programs/{program}/rules/{rule}', [LoyaltyProgramConfigController::class, 'destroyRule'])
                ->name('programs.rules.destroy');

            Route::post('programs/{program}/tiers', [LoyaltyProgramConfigController::class, 'storeTier'])
                ->name('programs.tiers.store');
            Route::put('programs/{program}/tiers/{tier}', [LoyaltyProgramConfigController::class, 'updateTier'])
                ->name('programs.tiers.update');
            Route::delete('programs/{program}/tiers/{tier}', [LoyaltyProgramConfigController::class, 'destroyTier'])
                ->name('programs.tiers.destroy');

            Route::post('programs/{program}/approval-policies', [LoyaltyProgramConfigController::class, 'storeApprovalPolicy'])
                ->name('programs.approval-policies.store');
            Route::put('programs/{program}/approval-policies/{policy}', [LoyaltyProgramConfigController::class, 'updateApprovalPolicy'])
                ->name('programs.approval-policies.update');
            Route::delete('programs/{program}/approval-policies/{policy}', [LoyaltyProgramConfigController::class, 'destroyApprovalPolicy'])
                ->name('programs.approval-policies.destroy');

            Route::post('programs/{program}/expiry-rules', [LoyaltyProgramConfigController::class, 'storeExpiryRule'])
                ->name('programs.expiry-rules.store');
            Route::put('programs/{program}/expiry-rules/{expiryRule}', [LoyaltyProgramConfigController::class, 'updateExpiryRule'])
                ->name('programs.expiry-rules.update');
            Route::delete('programs/{program}/expiry-rules/{expiryRule}', [LoyaltyProgramConfigController::class, 'destroyExpiryRule'])
                ->name('programs.expiry-rules.destroy');

            Route::post('programs/{program}/campaigns', [LoyaltyProgramConfigController::class, 'storeCampaign'])
                ->name('programs.campaigns.store');
            Route::put('programs/{program}/campaigns/{campaign}', [LoyaltyProgramConfigController::class, 'updateCampaign'])
                ->name('programs.campaigns.update');
            Route::delete('programs/{program}/campaigns/{campaign}', [LoyaltyProgramConfigController::class, 'destroyCampaign'])
                ->name('programs.campaigns.destroy');

            Route::get('transactions', [LoyaltyTransactionController::class, 'index'])
                ->name('transactions.index');
            Route::post('customers/{customer}/adjust', [LoyaltyTransactionController::class, 'adjust'])
                ->name('customers.adjust');
            Route::post('transactions/{transaction}/approve', [LoyaltyTransactionController::class, 'approve'])
                ->name('transactions.approve');
            Route::post('transactions/{transaction}/reject', [LoyaltyTransactionController::class, 'reject'])
                ->name('transactions.reject');
            Route::get('reports', [LoyaltyReportController::class, 'index'])->name('reports.index');
        });

        Route::post('stock-transfers/{stock_transfer}/ship', [StockTransferController::class, 'ship'])
            ->name('stock-transfers.ship');
        Route::post('stock-transfers/{stock_transfer}/receive', [StockTransferController::class, 'receive'])
            ->name('stock-transfers.receive');
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/reset-pos-pin-lockout', [UserController::class, 'resetPosPinLockout'])
            ->name('users.reset-pos-pin-lockout');
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::get('roles/{role}/clone', [RoleController::class, 'cloneForm'])->name('roles.clone');
        Route::post('roles/{role}/clone', [RoleController::class, 'cloneRole'])->name('roles.clone.store');
        Route::resource('permissions', PermissionController::class)->except(['show']);

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('settings/{group}', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings/{group}', [SettingsController::class, 'update'])->name('settings.update');

        Route::prefix('accounting')->name('accounting.')->group(function () {
            Route::get('chart-of-accounts', [ChartOfAccountController::class, 'index'])
                ->name('chart-of-accounts.index');
            Route::post('chart-of-accounts', [ChartOfAccountController::class, 'store'])
                ->name('chart-of-accounts.store');
            Route::put('chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'update'])
                ->name('chart-of-accounts.update');

            Route::get('account-mappings', [AccountMappingController::class, 'index'])
                ->name('account-mappings.index');
            Route::post('account-mappings', [AccountMappingController::class, 'store'])
                ->name('account-mappings.store');
            Route::put('account-mappings/{account_mapping}', [AccountMappingController::class, 'update'])
                ->name('account-mappings.update');
            Route::delete('account-mappings/{account_mapping}', [AccountMappingController::class, 'destroy'])
                ->name('account-mappings.destroy');

            Route::get('posting-rules', [PostingRuleController::class, 'index'])
                ->name('posting-rules.index');
            Route::get('posting-rules/{posting_rule_set}/duplicate', [PostingRuleController::class, 'create'])
                ->name('posting-rules.create');
            Route::post('posting-rules', [PostingRuleController::class, 'store'])
                ->name('posting-rules.store');
            Route::get('posting-rules/{posting_rule_set}/edit', [PostingRuleController::class, 'edit'])
                ->name('posting-rules.edit');
            Route::put('posting-rules/{posting_rule_set}', [PostingRuleController::class, 'update'])
                ->name('posting-rules.update');

            Route::get('journal-entries', [JournalEntryController::class, 'index'])
                ->name('journal-entries.index');
            Route::get('journal-entries/create', [JournalEntryController::class, 'create'])
                ->name('journal-entries.create');
            Route::post('journal-entries', [JournalEntryController::class, 'store'])
                ->name('journal-entries.store');
            Route::get('journal-entries/{journal_entry}/edit', [JournalEntryController::class, 'edit'])
                ->name('journal-entries.edit');
            Route::put('journal-entries/{journal_entry}', [JournalEntryController::class, 'update'])
                ->name('journal-entries.update');
            Route::delete('journal-entries/{journal_entry}', [JournalEntryController::class, 'destroy'])
                ->name('journal-entries.destroy');
            Route::get('journal-entries/{journal_entry}', [JournalEntryController::class, 'show'])
                ->name('journal-entries.show');
            Route::post('journal-entries/{journal_entry}/approve', [JournalEntryController::class, 'approve'])
                ->name('journal-entries.approve');
            Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post'])
                ->name('journal-entries.post');
            Route::post('journal-entries/{journal_entry}/reverse', [JournalEntryController::class, 'reverse'])
                ->name('journal-entries.reverse');

            Route::get('settings', [AccountingSettingsController::class, 'index'])
                ->name('settings.index');
            Route::put('settings', [AccountingSettingsController::class, 'update'])
                ->name('settings.update');
            Route::get('modules', [AccountingModulesController::class, 'index'])
                ->name('modules.index');
            Route::put('modules', [AccountingModulesController::class, 'update'])
                ->name('modules.update');
            Route::get('cost-layers/create', [CostLayerController::class, 'create'])
                ->name('cost-layers.create');
            Route::post('cost-layers', [CostLayerController::class, 'store'])
                ->name('cost-layers.store');
            Route::post('fiscal-years', [AccountingSettingsController::class, 'storeFiscalYear'])
                ->name('fiscal-years.store');
            Route::put('fiscal-years/{fiscal_year}', [AccountingSettingsController::class, 'updateFiscalYear'])
                ->name('fiscal-years.update');
            Route::post('fiscal-years/{fiscal_year}/close', [AccountingSettingsController::class, 'closeFiscalYear'])
                ->name('fiscal-years.close');
            Route::post('fiscal-years/{fiscal_year}/reopen-request', [AccountingSettingsController::class, 'requestReopen'])
                ->name('fiscal-years.reopen-request');
            Route::post('fiscal-year-reopen-requests/{fiscal_year_reopen_request}/approve', [AccountingSettingsController::class, 'approveReopen'])
                ->name('fiscal-year-reopen-requests.approve');
            Route::post('fiscal-year-reopen-requests/{fiscal_year_reopen_request}/reject', [AccountingSettingsController::class, 'rejectReopen'])
                ->name('fiscal-year-reopen-requests.reject');

            Route::post('imports/coa/{batch}/approve', [AccountingImportController::class, 'approveCoaBatch'])
                ->name('imports.coa.approve');
            Route::post('imports/opening-balances/{batch}/approve', [AccountingImportController::class, 'approveOpeningBalanceBatch'])
                ->name('imports.opening-balances.approve');
            Route::post('imports/opening-balances/reconciliations/{reconciliation}/approve-variance', [AccountingImportController::class, 'approveOpeningBalanceVariance'])
                ->name('imports.opening-balances.approve-variance');

            Route::middleware(['accounting-module:cost_centres'])->group(function () {
                Route::get('cost-centres', [CostCentreController::class, 'index'])
                    ->name('cost-centres.index');
                Route::post('cost-centres', [CostCentreController::class, 'store'])
                    ->name('cost-centres.store');
                Route::post('cost-centres/allocate', [CostCentreController::class, 'allocate'])
                    ->name('cost-centres.allocate');
                Route::put('cost-centres/{cost_centre}', [CostCentreController::class, 'update'])
                    ->name('cost-centres.update');
                Route::delete('cost-centres/{cost_centre}', [CostCentreController::class, 'destroy'])
                    ->name('cost-centres.destroy');
            });

            Route::get('reports', [AccountingReportController::class, 'index'])
                ->name('reports.index');
            Route::get('reports/trial-balance', [AccountingReportController::class, 'trialBalance'])
                ->name('reports.trial-balance');
            Route::get('reports/profit-and-loss', [AccountingReportController::class, 'profitAndLoss'])
                ->name('reports.profit-and-loss');
            Route::get('reports/balance-sheet', [AccountingReportController::class, 'balanceSheet'])
                ->name('reports.balance-sheet');
            Route::get('reports/general-ledger', [AccountingReportController::class, 'generalLedger'])
                ->name('reports.general-ledger');
            Route::get('reports/cost-centre-pl', [AccountingReportController::class, 'costCentrePl'])
                ->name('reports.cost-centre-pl');
            Route::get('reports/cash-flow', [AccountingReportController::class, 'cashFlow'])
                ->name('reports.cash-flow');
            Route::get('reports/ar-aging', [AccountingReportController::class, 'arAging'])
                ->name('reports.ar-aging');
            Route::get('reports/ap-aging', [AccountingReportController::class, 'apAging'])
                ->name('reports.ap-aging');
            Route::get('reports/bank-book', [AccountingReportController::class, 'bankBook'])
                ->name('reports.bank-book');
            Route::get('reports/inventory-valuation', [AccountingReportController::class, 'inventoryValuation'])
                ->name('reports.inventory-valuation');
            Route::get('reports/asset-register', [AccountingReportController::class, 'assetRegister'])
                ->name('reports.asset-register');
            Route::get('reports/fx-revaluation', [AccountingReportController::class, 'fxRevaluation'])
                ->name('reports.fx-revaluation');
            Route::get('reports/petty-cash', [AccountingReportController::class, 'pettyCash'])
                ->name('reports.petty-cash');
            Route::get('reports/cheque-status', [AccountingReportController::class, 'chequeStatus'])
                ->name('reports.cheque-status');
            Route::get('reports/audit-trail', [AccountingReportController::class, 'auditTrail'])
                ->name('reports.audit-trail');
            Route::get('reports/unposted-journals', [AccountingReportController::class, 'unpostedJournals'])
                ->name('reports.unposted-journals');
            Route::get('reports/journal-register', [AccountingReportController::class, 'journalRegister'])
                ->name('reports.journal-register');
            Route::get('reports/tax-return', [AccountingReportController::class, 'taxReturn'])
                ->name('reports.tax-return');
            Route::get('reports/{reportKey}/export', [AccountingReportController::class, 'export'])
                ->name('reports.export');

            Route::get('events', [AccountingEventController::class, 'index'])
                ->name('events.index');
            Route::post('events/{accounting_event}/retry', [AccountingEventController::class, 'retry'])
                ->name('events.retry');

            Route::middleware(['accounting-module:credit_notes'])->group(function () {
                Route::get('credit-notes', [CreditNoteController::class, 'index'])
                    ->name('credit-notes.index');
                Route::get('credit-notes/create', [CreditNoteController::class, 'create'])
                    ->name('credit-notes.create');
                Route::post('credit-notes', [CreditNoteController::class, 'store'])
                    ->name('credit-notes.store');
            });

            Route::middleware(['accounting-module:tax'])->group(function () {
                Route::get('tax-types', [TaxTypeController::class, 'index'])
                    ->name('tax-types.index');
                Route::post('tax-types', [TaxTypeController::class, 'store'])
                    ->name('tax-types.store');
            });

            Route::middleware(['accounting-module:bank_reconciliation'])->group(function () {
                Route::get('bank-accounts', [BankAccountController::class, 'index'])
                    ->name('bank-accounts.index');
                Route::post('bank-accounts', [BankAccountController::class, 'store'])
                    ->name('bank-accounts.store');

                Route::get('reconciliation', [BankReconciliationController::class, 'index'])
                    ->name('reconciliation.index');
                Route::post('reconciliation/bank-accounts/{bank_account}/import', [BankReconciliationController::class, 'import'])
                    ->name('reconciliation.import');
                Route::post('reconciliation/lines/{bank_statement_line}/match', [BankReconciliationController::class, 'match'])
                    ->name('reconciliation.match');
            });

            Route::middleware(['accounting-module:multi_currency'])->group(function () {
                Route::get('currencies', [CurrencyController::class, 'index'])
                    ->name('currencies.index');
                Route::post('currencies', [CurrencyController::class, 'store'])
                    ->name('currencies.store');
                Route::post('currencies/rates', [CurrencyController::class, 'storeRate'])
                    ->name('currencies.rates.store');
            });

            Route::middleware(['accounting-module:petty_cash'])->group(function () {
                Route::get('petty-cash', [PettyCashController::class, 'index'])
                    ->name('petty-cash.index');
                Route::post('petty-cash/registers', [PettyCashController::class, 'storeRegister'])
                    ->name('petty-cash.registers.store');
                Route::post('petty-cash/registers/{petty_cash_register}/vouchers', [PettyCashController::class, 'storeVoucher'])
                    ->name('petty-cash.vouchers.store');
                Route::post('petty-cash/vouchers/{petty_cash_voucher}/approve', [PettyCashController::class, 'approveVoucher'])
                    ->name('petty-cash.vouchers.approve');
                Route::post('petty-cash/vouchers/{petty_cash_voucher}/reject', [PettyCashController::class, 'rejectVoucher'])
                    ->name('petty-cash.vouchers.reject');
            });

            Route::middleware(['accounting-module:cheques'])->group(function () {
                Route::get('cheques', [ChequeController::class, 'index'])
                    ->name('cheques.index');
                Route::post('cheques', [ChequeController::class, 'store'])
                    ->name('cheques.store');
                Route::patch('cheques/{cheque}/status', [ChequeController::class, 'updateStatus'])
                    ->name('cheques.status.update');
            });

            Route::middleware(['accounting-module:fixed_assets'])->group(function () {
                Route::get('fixed-assets', [FixedAssetController::class, 'index'])
                    ->name('fixed-assets.index');
                Route::post('fixed-assets', [FixedAssetController::class, 'store'])
                    ->name('fixed-assets.store');
                Route::post('fixed-assets/run-depreciation', [FixedAssetController::class, 'runDepreciation'])
                    ->name('fixed-assets.run-depreciation');
                Route::post('fixed-assets/categories', [FixedAssetController::class, 'storeCategory'])
                    ->name('fixed-assets.categories.store');
                Route::post('fixed-assets/{fixed_asset}/dispose', [FixedAssetController::class, 'dispose'])
                    ->name('fixed-assets.dispose');
            });
        });

        $registerImportExport = require __DIR__.'/import-export.php';
        $registerImportExport('import-export.');
    });
