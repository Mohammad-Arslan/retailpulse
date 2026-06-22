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
    require __DIR__.'/admin.php';
});

Route::redirect('/pos', '/admin/pos');

Route::get('/invoice/{publicToken}', [PublicInvoiceController::class, 'show'])
    ->name('invoice.public');

Route::get('/invoice/{publicToken}/print', [InvoicePrintController::class, 'show'])
    ->name('invoice.print');

require __DIR__.'/auth.php';
