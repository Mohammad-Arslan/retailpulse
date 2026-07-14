<?php

declare(strict_types=1);

use App\Http\Controllers\HelpSupport\HelpSupportController;
use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicInvoiceController;
use App\Services\Dashboard\HomeRouteResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::put('/locale', [LocaleController::class, 'update'])
    ->middleware('web')
    ->name('locale.update');

Route::get('/', function (HomeRouteResolver $home) {
    if (Auth::check()) {
        return redirect()->to($home->url(Auth::user()));
    }

    return redirect()->route('login');
});

Route::get('/home', function (HomeRouteResolver $home) {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->to($home->url(Auth::user()));
});

Route::get('/dashboard', function (HomeRouteResolver $home) {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->to($home->url(Auth::user()));
})->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::prefix('help-support')->name('help-support.')->group(function () {
        Route::get('/', [HelpSupportController::class, 'index'])
            ->name('index');
        Route::get('/guides/accounting', [HelpSupportController::class, 'accountingGuide'])
            ->name('guides.accounting');
        Route::get('/guides/customers-loyalty', [HelpSupportController::class, 'customersLoyaltyGuide'])
            ->name('guides.customers-loyalty');
        Route::get('/guides/inventory-catalogue', [HelpSupportController::class, 'inventoryCatalogueGuide'])
            ->name('guides.inventory-catalogue');
        Route::get('/guides/put-product-in-stock', [HelpSupportController::class, 'putProductInStockGuide'])
            ->name('guides.put-product-in-stock');
        Route::post('/guides/{guide}/ask', [HelpSupportController::class, 'ask'])
            ->middleware('throttle:ai-guide-ask')
            ->name('guides.ask');
    });

    require __DIR__.'/admin.php';
});

Route::redirect('/pos', '/admin/pos');

Route::get('/invoice/{publicToken}', [PublicInvoiceController::class, 'show'])
    ->name('invoice.public');

Route::get('/invoice/{publicToken}/print', [InvoicePrintController::class, 'show'])
    ->name('invoice.print');

require __DIR__.'/auth.php';
