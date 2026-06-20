<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

final class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['code' => 'SUP-ACME', 'name' => 'Acme Wholesale'],
            ['code' => 'SUP-GLOBAL', 'name' => 'Global Distributors'],
            ['code' => 'SUP-LOCAL', 'name' => 'Local Goods Co.'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->firstOrCreate(
                ['code' => $supplier['code']],
                [
                    'name' => $supplier['name'],
                    'slug' => str($supplier['name'])->slug()->toString(),
                    'is_active' => true,
                ],
            );
        }
    }
}
