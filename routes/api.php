<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Pos\InventoryController as PosInventoryController;
use App\Http\Controllers\Api\V1\InventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->name('api.v1.')
    ->group(function () {
        Route::post('inventory/check-availability', [InventoryController::class, 'checkAvailability'])
            ->name('inventory.check-availability');
    });

Route::prefix('pos')
    ->middleware('auth:sanctum')
    ->name('api.pos.')
    ->group(function () {
        Route::post('stock-check', [PosInventoryController::class, 'stockCheck'])
            ->name('stock-check');
        Route::post('stock-deduct', [PosInventoryController::class, 'stockDeduct'])
            ->name('stock-deduct');
    });

/*
| Legacy /api/import-export/* — kept for cached JS bundles. Uses web session (not Sanctum).
*/
Route::middleware(['web', 'auth', 'admin', 'branch.context'])
    ->group(function () {
        $registerImportExport = require __DIR__.'/import-export.php';
        $registerImportExport('import-export.');
    });
