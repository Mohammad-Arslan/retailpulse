<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('login');
});

Route::redirect('/home', '/admin/dashboard');
Route::redirect('/dashboard', '/admin/dashboard');

Route::middleware('auth')->group(function () {
    require __DIR__.'/admin.php';
});

require __DIR__.'/auth.php';
