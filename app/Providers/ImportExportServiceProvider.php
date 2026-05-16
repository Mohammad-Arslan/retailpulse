<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ImportExport\Handlers\InventoryExportHandler;
use App\Services\ImportExport\Handlers\InventoryImportHandler;
use App\Services\ImportExport\Handlers\ProductExportHandler;
use App\Services\ImportExport\Handlers\ProductImportHandler;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use App\Services\ImportExport\Validation\RuleResolverRegistry;
use Illuminate\Support\ServiceProvider;

final class ImportExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImportExportStorageManager::class);
        $this->app->singleton(RuleResolverRegistry::class);
        $this->app->singleton(DynamicRuleEngine::class);
    }

    public function boot(): void
    {
        ImportExportRegistry::register('products', ProductImportHandler::class, ProductExportHandler::class);
        ImportExportRegistry::register('inventory', InventoryImportHandler::class, InventoryExportHandler::class);
    }
}
