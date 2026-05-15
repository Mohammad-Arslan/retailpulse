<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BranchContextController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin', 'branch.context'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::put('/branch-context', [BranchContextController::class, 'update'])
            ->name('branch-context.update');

        Route::resource('branches', BranchController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::get('roles/{role}/clone', [RoleController::class, 'cloneForm'])->name('roles.clone');
        Route::post('roles/{role}/clone', [RoleController::class, 'cloneRole'])->name('roles.clone.store');
        Route::resource('permissions', PermissionController::class)->except(['show']);
    });
