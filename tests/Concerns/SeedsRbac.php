<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

trait SeedsRbac
{
    protected function seedRbac(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }
}
