<?php

use App\Providers\AppServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\ImportExportServiceProvider;
use App\Providers\NavigationServiceProvider;
use App\Providers\SearchServiceProvider;

return [
    AppServiceProvider::class,
    DashboardServiceProvider::class,
    HorizonServiceProvider::class,
    ImportExportServiceProvider::class,
    NavigationServiceProvider::class,
    SearchServiceProvider::class,
];
