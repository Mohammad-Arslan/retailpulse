<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\WarehouseType;
use App\Models\Branch;
use App\Support\OperatingHours;
use Illuminate\Database\Seeder;

final class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Head Office',
                'code' => 'HQ',
                'address' => '100 Main Street',
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'warehouse_name' => 'Main Warehouse',
                'warehouse_code' => 'MAIN',
                'warehouse_type' => WarehouseType::Backroom,
            ],
            [
                'name' => 'Downtown Store',
                'code' => 'DT01',
                'address' => '42 Market Avenue',
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'warehouse_name' => 'Store Floor',
                'warehouse_code' => 'FLOOR',
                'warehouse_type' => WarehouseType::SalesFloor,
            ],
        ];

        foreach ($branches as $data) {
            $branch = Branch::query()->firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'currency' => $data['currency'],
                    'timezone' => $data['timezone'],
                    'operating_hours' => OperatingHours::defaults(),
                    'receipt_footer' => 'Thank you for shopping with us.',
                    'is_active' => true,
                ],
            );

            $branch->warehouses()->firstOrCreate(
                ['code' => $data['warehouse_code']],
                [
                    'name' => $data['warehouse_name'],
                    'type' => $data['warehouse_type']->value,
                    'is_default' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
