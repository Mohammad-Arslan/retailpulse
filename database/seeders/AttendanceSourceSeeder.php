<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AttendanceSource;
use Illuminate\Database\Seeder;

final class AttendanceSourceSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['driver' => 'pos_pin', 'name' => 'POS PIN', 'status' => 'active'],
            ['driver' => 'manual', 'name' => 'Manual Entry', 'status' => 'active'],
            ['driver' => 'biometric', 'name' => 'Biometric Device', 'status' => 'inactive'],
            ['driver' => 'mobile', 'name' => 'Mobile App', 'status' => 'inactive'],
            ['driver' => 'import', 'name' => 'Bulk Import', 'status' => 'inactive'],
        ];

        foreach ($defaults as $source) {
            AttendanceSource::query()->firstOrCreate(
                [
                    'driver' => $source['driver'],
                    'branch_id' => null,
                ],
                [
                    'name' => $source['name'],
                    'config_json' => [],
                    'status' => $source['status'],
                ],
            );
        }
    }
}
