<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\DashboardServiceProvider::class,
    App\Providers\ImportExportServiceProvider::class,
    App\Providers\NavigationServiceProvider::class,
    App\Providers\SearchServiceProvider::class,
];
