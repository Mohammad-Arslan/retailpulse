<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\InventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->name('api.v1.')
    ->group(function () {
        Route::post('inventory/check-availability', [InventoryController::class, 'checkAvailability'])
            ->name('inventory.check-availability');
    });
