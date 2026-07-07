<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FiscalYearStatus;
use App\Models\FiscalYear;
use App\Models\OrganizationEntity;
use Illuminate\Database\Seeder;

final class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $entity = OrganizationEntity::query()->first();

        if ($entity === null) {
            return;
        }

        $year = (int) now()->format('Y');

        FiscalYear::query()->firstOrCreate(
            [
                'name' => "FY {$year}",
                'legal_entity_id' => $entity->id,
            ],
            [
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => FiscalYearStatus::Open,
            ],
        );
    }
}
