<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

final class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->firstOrCreate(
                ['code' => $currency['code']],
                [...$currency, 'status' => 'active'],
            );
        }
    }
}
