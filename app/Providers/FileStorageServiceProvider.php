<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Storage\FileStorageDiskRegistrar;
use Illuminate\Support\ServiceProvider;

final class FileStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FileStorageDiskRegistrar::class);
    }
}
