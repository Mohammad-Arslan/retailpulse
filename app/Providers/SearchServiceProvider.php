<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Search\Contracts\SearchProvider;
use App\Services\Search\Providers\BankAccountSearchProvider;
use App\Services\Search\Providers\BranchSearchProvider;
use App\Services\Search\Providers\CustomerSearchProvider;
use App\Services\Search\Providers\FixedAssetSearchProvider;
use App\Services\Search\Providers\GoodsReceiptSearchProvider;
use App\Services\Search\Providers\JournalEntrySearchProvider;
use App\Services\Search\Providers\PageSearchProvider;
use App\Services\Search\Providers\ProductSearchProvider;
use App\Services\Search\Providers\PurchaseOrderSearchProvider;
use App\Services\Search\Providers\SaleSearchProvider;
use App\Services\Search\Providers\StockTransferSearchProvider;
use App\Services\Search\Providers\SupplierSearchProvider;
use App\Services\Search\Providers\UserSearchProvider;
use App\Services\Search\Providers\WarehouseSearchProvider;
use App\Services\Search\SearchManager;
use App\Services\Search\SearchRegistry;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    /**
     * @var list<class-string<SearchProvider>>
     */
    private const PROVIDERS = [
        PageSearchProvider::class,
        ProductSearchProvider::class,
        CustomerSearchProvider::class,
        SupplierSearchProvider::class,
        SaleSearchProvider::class,
        PurchaseOrderSearchProvider::class,
        GoodsReceiptSearchProvider::class,
        StockTransferSearchProvider::class,
        WarehouseSearchProvider::class,
        BranchSearchProvider::class,
        JournalEntrySearchProvider::class,
        BankAccountSearchProvider::class,
        UserSearchProvider::class,
        FixedAssetSearchProvider::class,
    ];

    public function register(): void
    {
        $this->app->singleton(SearchRegistry::class, function ($app): SearchRegistry {
            $registry = new SearchRegistry;

            foreach (self::PROVIDERS as $providerClass) {
                $registry->register($app->make($providerClass));
            }

            return $registry;
        });

        $this->app->singleton(SearchManager::class);
    }
}
