<?php

declare(strict_types=1);

use App\Http\Controllers\ImportExport\ExportController;
use App\Http\Controllers\ImportExport\ImportJobController;
use App\Http\Controllers\ImportExport\ImportWizardController;
use App\Http\Controllers\ImportExport\TemplateController;
use Illuminate\Support\Facades\Route;

/**
 * Shared import/export routes. Included from admin (session) and api (legacy/cached clients).
 *
 * @param  string  $namePrefix  Route name prefix, e.g. "admin.import-export." or "import-export."
 */
return function (string $namePrefix): void {
    Route::prefix('import-export')->name($namePrefix)->group(function () {
        Route::post('imports/upload', [ImportWizardController::class, 'upload'])->name('imports.upload');
        Route::get('imports/{ulid}/headers', [ImportWizardController::class, 'headers'])->name('imports.headers');
        Route::post('imports/{ulid}/mapping', [ImportWizardController::class, 'saveMapping'])->name('imports.mapping');
        Route::get('imports/{ulid}/rules', [ImportWizardController::class, 'getRules'])->name('imports.rules');
        Route::post('imports/{ulid}/rules', [ImportWizardController::class, 'saveRules'])->name('imports.rules.save');
        Route::post('imports/{ulid}/confirm', [ImportWizardController::class, 'confirm'])->name('imports.confirm');

        Route::get('jobs', [ImportJobController::class, 'index'])->name('jobs.index');
        Route::get('jobs/latest-import/{entityType}', [ImportJobController::class, 'latestImport'])->name('jobs.latest-import');
        Route::get('jobs/{ulid}', [ImportJobController::class, 'show'])->name('jobs.show');
        Route::get('jobs/{ulid}/row-errors', [ImportJobController::class, 'rowErrors'])->name('jobs.row-errors');
        Route::post('jobs/{ulid}/cancel', [ImportJobController::class, 'cancel'])->name('jobs.cancel');
        Route::get('jobs/{ulid}/errors', [ExportController::class, 'errors'])->name('errors');
        Route::get('jobs/{ulid}/download', [ExportController::class, 'download'])->name('jobs.download');

        Route::post('exports', [ExportController::class, 'initiate'])->name('exports.initiate');
        Route::get('templates/{entity}', [TemplateController::class, 'download'])->name('templates.download');

        Route::get('stream', [ImportJobController::class, 'stream'])
            ->middleware('signed')
            ->name('stream');
    });
};
