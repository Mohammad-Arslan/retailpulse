<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\WarehouseType;
use App\Models\Branch;
use Illuminate\Database\Seeder;

final class DemoWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $hq = Branch::query()->where('code', 'HQ')->first();

        if ($hq === null) {
            return;
        }

        $hq->warehouses()->firstOrCreate(
            ['code' => 'OVERFLOW'],
            [
                'name' => 'Overflow Storage',
                'type' => WarehouseType::Offsite->value,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $hq->warehouses()->where('code', 'MAIN')->update([
            'type' => WarehouseType::Backroom->value,
        ]);

        $downtown = Branch::query()->where('code', 'DT01')->first();

        if ($downtown !== null) {
            $downtown->warehouses()->where('code', 'FLOOR')->update([
                'type' => WarehouseType::SalesFloor->value,
            ]);
        }
    }
}
