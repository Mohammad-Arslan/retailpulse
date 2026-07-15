<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DocumentSequence;
use App\Models\Grade;
use App\Models\OrganizationEntity;
use Illuminate\Database\Seeder;

final class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $entity = OrganizationEntity::query()->where('status', 'active')->orderBy('id')->first();
        if ($entity === null) {
            return;
        }

        $currency = $entity->functional_currency_code ?: 'USD';

        $grades = [
            [
                'code' => 'GRADE-00001',
                'name' => 'Staff',
                'rank' => 10,
                'min_amount' => 30000,
                'mid_amount' => 45000,
                'max_amount' => 60000,
            ],
            [
                'code' => 'GRADE-00002',
                'name' => 'Senior Staff',
                'rank' => 20,
                'min_amount' => 50000,
                'mid_amount' => 70000,
                'max_amount' => 90000,
            ],
            [
                'code' => 'GRADE-00003',
                'name' => 'Supervisor',
                'rank' => 30,
                'min_amount' => 75000,
                'mid_amount' => 100000,
                'max_amount' => 125000,
            ],
            [
                'code' => 'GRADE-00004',
                'name' => 'Manager',
                'rank' => 40,
                'min_amount' => 110000,
                'mid_amount' => 150000,
                'max_amount' => 190000,
            ],
            [
                'code' => 'GRADE-00005',
                'name' => 'Director',
                'rank' => 50,
                'min_amount' => 180000,
                'mid_amount' => 240000,
                'max_amount' => 300000,
            ],
        ];

        foreach ($grades as $grade) {
            Grade::query()->updateOrCreate(
                [
                    'legal_entity_id' => $entity->id,
                    'code' => $grade['code'],
                ],
                [
                    'name' => $grade['name'],
                    'rank' => $grade['rank'],
                    'currency_code' => $currency,
                    'min_amount' => $grade['min_amount'],
                    'mid_amount' => $grade['mid_amount'],
                    'max_amount' => $grade['max_amount'],
                    'enforce_salary_band' => false,
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'effective_to' => null,
                    'status' => 'active',
                ],
            );
        }

        $this->syncSequence('grade', 'GRADE', count($grades) + 1);
    }

    private function syncSequence(string $documentType, string $prefix, int $nextNumber): void
    {
        DocumentSequence::query()->updateOrCreate(
            [
                'document_type' => $documentType,
                'branch_id' => null,
                'legal_entity_id' => null,
                'fiscal_year_id' => null,
            ],
            [
                'prefix' => $prefix,
                'next_number' => $nextNumber,
                'status' => 'active',
            ],
        );
    }
}
