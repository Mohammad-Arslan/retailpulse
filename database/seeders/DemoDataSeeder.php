<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo / dummy data for local testing and QA.
 *
 * Prerequisites (run first):
 *   php artisan migrate
 *   php artisan db:seed
 *
 * Then:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Or run everything in order:
 *   php artisan migrate:fresh --seed --seeder=DemoDataSeeder
 *   (DemoDataSeeder chains DatabaseSeeder internally)
 */
final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        // Avoid Reverb/Pusher connection errors when inventory events fire during seeding.
        config(['broadcasting.default' => 'null']);

        $this->call([
            DemoUserSeeder::class,
            DemoCatalogSeeder::class,
            DemoWarehouseSeeder::class,
            DemoInventorySeeder::class,
            DemoCustomerSeeder::class,
            DemoLoyaltySeeder::class,
            DemoProcurementSeeder::class,
        ]);
    }
}
