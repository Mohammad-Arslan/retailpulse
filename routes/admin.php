<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BranchContextController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CatalogBulkController;
use App\Http\Controllers\Admin\CategoryController;
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
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'branch.context', 'pos.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    });

Route::middleware(['auth', 'admin', 'branch.context'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::put('/branch-context', [BranchContextController::class, 'update'])
            ->name('branch-context.update');

        Route::resource('branches', BranchController::class)->except(['show']);
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
