<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\ImportExport\ExportController;
use App\Http\Controllers\ImportExport\ImportJobController;
use App\Http\Controllers\ImportExport\ImportWizardController;
use App\Http\Controllers\ImportExport\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->name('api.v1.')
    ->group(function () {
        Route::post('inventory/check-availability', [InventoryController::class, 'checkAvailability'])
            ->name('inventory.check-availability');
    });

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('import-export')->name('import-export.')->group(function () {
        Route::post('imports/upload', [ImportWizardController::class, 'upload']);
        Route::get('imports/{ulid}/headers', [ImportWizardController::class, 'headers']);
        Route::post('imports/{ulid}/mapping', [ImportWizardController::class, 'saveMapping']);
        Route::get('imports/{ulid}/rules', [ImportWizardController::class, 'getRules']);
        Route::post('imports/{ulid}/rules', [ImportWizardController::class, 'saveRules']);
        Route::post('imports/{ulid}/confirm', [ImportWizardController::class, 'confirm']);

        Route::get('jobs', [ImportJobController::class, 'index']);
        Route::get('jobs/{ulid}', [ImportJobController::class, 'show']);
        Route::post('jobs/{ulid}/cancel', [ImportJobController::class, 'cancel']);
        Route::get('jobs/{ulid}/errors', [ExportController::class, 'errors'])->name('errors');
        Route::get('jobs/{ulid}/download', [ExportController::class, 'download']);

        Route::post('exports', [ExportController::class, 'initiate']);
        Route::get('templates/{entity}', [TemplateController::class, 'download']);

        Route::get('stream', [ImportJobController::class, 'stream'])
            ->middleware('signed')
            ->name('stream');
    });
});
