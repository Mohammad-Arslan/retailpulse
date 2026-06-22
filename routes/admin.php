<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ArAgingController;
use App\Http\Controllers\Admin\BranchContextController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CatalogBulkController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CountScheduleRuleController;
use App\Http\Controllers\Admin\CountSessionController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\CustomerWalletController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GoodsReceivingNoteController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PoMatchController;
use App\Http\Controllers\Admin\ProcurementReportController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierInvoiceController;
use App\Http\Controllers\Admin\SupplierPaymentController;
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

        Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
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

        $registerImportExport = require __DIR__.'/import-export.php';
        $registerImportExport('import-export.');
    });
