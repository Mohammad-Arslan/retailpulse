<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Navigation\Catalog\AdminNavigationCatalog;
use App\Services\Navigation\NavigationComposer;
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Support\ServiceProvider;

final class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NavigationRegistry::class, function (): NavigationRegistry {
            $registry = new NavigationRegistry;
            AdminNavigationCatalog::register($registry);

            return $registry;
        });

        $this->app->singleton(NavigationComposer::class);
    }
}
