<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ImportExport\Handlers\BrandExportHandler;
use App\Services\ImportExport\Handlers\BrandImportHandler;
use App\Services\ImportExport\Handlers\CategoryExportHandler;
use App\Services\ImportExport\Handlers\CategoryImportHandler;
use App\Services\ImportExport\Handlers\CoaExportHandler;
use App\Services\ImportExport\Handlers\CoaImportHandler;
use App\Services\ImportExport\Handlers\CustomerExportHandler;
use App\Services\ImportExport\Handlers\CustomerImportHandler;
use App\Services\ImportExport\Handlers\InventoryAdjustmentExportHandler;
use App\Services\ImportExport\Handlers\InventoryAdjustmentImportHandler;
use App\Services\ImportExport\Handlers\InventoryExportHandler;
use App\Services\ImportExport\Handlers\InventoryImportHandler;
use App\Services\ImportExport\Handlers\OpeningBalanceExportHandler;
use App\Services\ImportExport\Handlers\OpeningBalanceImportHandler;
use App\Services\ImportExport\Handlers\ProductExportHandler;
use App\Services\ImportExport\Handlers\ProductImportHandler;
use App\Services\ImportExport\Handlers\SupplierExportHandler;
use App\Services\ImportExport\Handlers\SupplierImportHandler;
use App\Services\ImportExport\Handlers\SupplierPriceListExportHandler;
use App\Services\ImportExport\Handlers\SupplierPriceListImportHandler;
use App\Services\ImportExport\Handlers\UnitImportHandler;
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
        ImportExportRegistry::register('categories', CategoryImportHandler::class, CategoryExportHandler::class);
        ImportExportRegistry::register('brands', BrandImportHandler::class, BrandExportHandler::class);
        ImportExportRegistry::register('units', UnitImportHandler::class, UnitExportHandler::class);
        ImportExportRegistry::register('products', ProductImportHandler::class, ProductExportHandler::class);
        ImportExportRegistry::register('customers', CustomerImportHandler::class, CustomerExportHandler::class);
        ImportExportRegistry::register('suppliers', SupplierImportHandler::class, SupplierExportHandler::class);
        ImportExportRegistry::register(
            'supplier-price-lists',
            SupplierPriceListImportHandler::class,
            SupplierPriceListExportHandler::class,
        );
        ImportExportRegistry::register('inventory', InventoryImportHandler::class, InventoryExportHandler::class);
        ImportExportRegistry::register(
            'inventory-adjustments',
            InventoryAdjustmentImportHandler::class,
            InventoryAdjustmentExportHandler::class,
        );
        ImportExportRegistry::register('coa', CoaImportHandler::class, CoaExportHandler::class);
        ImportExportRegistry::register(
            'opening-balances',
            OpeningBalanceImportHandler::class,
            OpeningBalanceExportHandler::class,
        );
    }
}
