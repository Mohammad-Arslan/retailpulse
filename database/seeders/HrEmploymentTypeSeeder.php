<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HrEmploymentType;
use Illuminate\Database\Seeder;

final class HrEmploymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['code' => 'full_time', 'name' => 'Full Time'],
            ['code' => 'part_time', 'name' => 'Part Time'],
            ['code' => 'contract', 'name' => 'Contract'],
            ['code' => 'hourly', 'name' => 'Hourly'],
        ];

        foreach ($defaults as $row) {
            HrEmploymentType::query()->updateOrCreate(
                ['legal_entity_id' => null, 'code' => $row['code']],
                ['name' => $row['name'], 'status' => 'active'],
            );
        }
    }
}
