<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BranchContextController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CatalogBulkController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CountScheduleRuleController;
use App\Http\Controllers\Admin\CountSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StockTransferController;
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
