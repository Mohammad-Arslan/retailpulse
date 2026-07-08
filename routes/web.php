<?php

declare(strict_types=1);

use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicInvoiceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::put('/locale', [LocaleController::class, 'update'])
    ->middleware('web')
    ->name('locale.update');

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();

        if ($user?->can('admin.access')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user?->can('pos.access')) {
            return redirect()->route('admin.pos.index');
        }
    }

    return redirect()->route('login');
});

Route::redirect('/home', '/admin/dashboard');
Route::redirect('/dashboard', '/admin/dashboard');

Route::middleware('auth')->group(function () {
    Route::prefix('help-support')->name('help-support.')->group(function () {
        Route::get('/', [\App\Http\Controllers\HelpSupport\HelpSupportController::class, 'index'])
            ->name('index');
        Route::get('/guides/accounting', [\App\Http\Controllers\HelpSupport\HelpSupportController::class, 'accountingGuide'])
            ->name('guides.accounting');
        Route::get('/guides/customers-loyalty', [\App\Http\Controllers\HelpSupport\HelpSupportController::class, 'customersLoyaltyGuide'])
            ->name('guides.customers-loyalty');
        Route::get('/guides/inventory-catalogue', [\App\Http\Controllers\HelpSupport\HelpSupportController::class, 'inventoryCatalogueGuide'])
            ->name('guides.inventory-catalogue');
        Route::get('/guides/put-product-in-stock', [\App\Http\Controllers\HelpSupport\HelpSupportController::class, 'putProductInStockGuide'])
            ->name('guides.put-product-in-stock');
    });

    require __DIR__.'/admin.php';
});

Route::redirect('/pos', '/admin/pos');

Route::get('/invoice/{publicToken}', [PublicInvoiceController::class, 'show'])
    ->name('invoice.public');

Route::get('/invoice/{publicToken}/print', [InvoicePrintController::class, 'show'])
    ->name('invoice.print');

require __DIR__.'/auth.php';
