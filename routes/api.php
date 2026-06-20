<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Checkout\CheckoutController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerWalletController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\Pos\CartController;
use App\Http\Controllers\Api\V1\Pos\CartItemController;
use App\Http\Controllers\Api\V1\Pos\PinController;
use App\Http\Controllers\Api\V1\Pos\ProductCatalogController;
use App\Http\Controllers\Api\V1\Pos\ProductSearchController;
use App\Http\Controllers\Api\V1\Sales\HistoricalSaleImportController;
use App\Http\Controllers\Api\V1\Sales\SaleController;
use App\Http\Controllers\Api\V1\Sales\SaleExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->name('api.v1.')
    ->group(function () {
        Route::post('inventory/check-availability', [InventoryController::class, 'checkAvailability'])
            ->name('inventory.check-availability');
    });

/*
| POS API — uses web session auth so it works from any domain the app is served on,
| identical to the import/export API pattern. No Sanctum stateful domain config needed.
*/
Route::prefix('v1/pos')
    ->middleware(['web', 'auth'])
    ->name('api.v1.pos.')
    ->group(function () {
        // PIN routes need only auth — they are accessible to any authenticated user
        Route::post('pin/verify', [PinController::class, 'verify'])->name('pin.verify');
        Route::post('pin/set', [PinController::class, 'setPin'])->name('pin.set');
        Route::get('pin/status', [PinController::class, 'status'])->name('pin.status');
        Route::post('pin/reset/{userId}', [PinController::class, 'resetLockout'])->name('pin.reset');

        // All remaining POS routes require pos.access
        Route::middleware('pos.access')->group(function () {
            Route::get('products/search', ProductSearchController::class)->name('products.search');
            Route::get('products/catalog', ProductCatalogController::class)->name('products.catalog');

            Route::get('carts', [CartController::class, 'index'])->name('carts.index');
            Route::post('carts', [CartController::class, 'store'])->name('carts.store');
            Route::get('carts/{cartId}', [CartController::class, 'show'])->name('carts.show');
            Route::patch('carts/{cartId}/suspend', [CartController::class, 'suspend'])->name('carts.suspend');
            Route::patch('carts/{cartId}/resume', [CartController::class, 'resume'])->name('carts.resume');
            Route::patch('carts/{cartId}/void', [CartController::class, 'void'])->name('carts.void');
            Route::patch('carts/{cartId}/complete', [CartController::class, 'complete'])->name('carts.complete');
            Route::patch('carts/{cartId}/reopen', [CartController::class, 'reopen'])->name('carts.reopen');
            Route::post('carts/{cartId}/checkout', [CartController::class, 'checkout'])->name('carts.checkout');
            Route::get('carts/{cartId}/stock-warnings', [CartController::class, 'stockWarnings'])->name('carts.stock-warnings');

            Route::post('carts/{cartId}/items', [CartItemController::class, 'store'])->name('cart-items.store');
            Route::patch('carts/{cartId}/items/{itemId}', [CartItemController::class, 'update'])->name('cart-items.update');
            Route::delete('carts/{cartId}/items/{itemId}', [CartItemController::class, 'destroy'])->name('cart-items.destroy');
        }); // end pos.access group
    });

Route::prefix('v1')
    ->middleware(['web', 'auth', 'pos.access'])
    ->name('api.v1.')
    ->group(function () {
        Route::get('checkout/{cartId}', [CheckoutController::class, 'show'])->name('checkout.show');
        Route::post('checkout/{cartId}/confirm', [CheckoutController::class, 'confirm'])->name('checkout.confirm');
        Route::post('checkout/{cartId}/abandon', [CheckoutController::class, 'abandon'])->name('checkout.abandon');

        Route::post('sales/{id}/payments', [SaleController::class, 'addPayment'])->name('sales.payments.store');
        Route::post('sales/{id}/void', [SaleController::class, 'void'])->name('sales.void');
    });

Route::prefix('v1')
    ->middleware(['web', 'auth'])
    ->name('api.v1.')
    ->group(function () {
        Route::get('customers', [CustomerController::class, 'search'])->name('customers.search');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/credit-check', [CustomerController::class, 'creditCheck'])
            ->name('customers.credit-check');
        Route::post('customers/{customer}/wallet/top-up', [CustomerWalletController::class, 'topUp'])
            ->name('customers.wallet.top-up');

        // Static sale routes must precede the {id} wildcard
        Route::post('sales/import-historical', HistoricalSaleImportController::class)->name('sales.import-historical');
        Route::get('sales/export', SaleExportController::class)->name('sales.export');

        Route::get('sales/{id}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('sales/{id}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
        Route::post('sales/{id}/invoice/pdf', [SaleController::class, 'generatePdf'])->name('sales.invoice.pdf');
        Route::post('sales/{id}/invoice/share', [SaleController::class, 'share'])->name('sales.invoice.share');
    });

/*
| Legacy /api/import-export/* — kept for cached JS bundles. Uses web session (not Sanctum).
*/
Route::middleware(['web', 'auth', 'admin', 'branch.context'])
    ->group(function () {
        $registerImportExport = require __DIR__.'/import-export.php';
        $registerImportExport('import-export.');
    });
