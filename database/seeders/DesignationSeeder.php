<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Designation;
use App\Models\DocumentSequence;
use App\Models\Grade;
use App\Models\OrganizationEntity;
use Illuminate\Database\Seeder;

final class DesignationSeeder extends Seeder
{
    public function run(): void
    {
        $entity = OrganizationEntity::query()->where('status', 'active')->orderBy('id')->first();
        if ($entity === null) {
            return;
        }

        $gradesByCode = Grade::query()
            ->where('legal_entity_id', $entity->id)
            ->where('status', 'active')
            ->get(['id', 'code'])
            ->keyBy('code');

        $designations = [
            [
                'code' => 'DESIG-00001',
                'name' => 'Cashier',
                'default_grade_code' => 'GRADE-00001',
            ],
            [
                'code' => 'DESIG-00002',
                'name' => 'Sales Associate',
                'default_grade_code' => 'GRADE-00001',
            ],
            [
                'code' => 'DESIG-00003',
                'name' => 'Store Supervisor',
                'default_grade_code' => 'GRADE-00003',
            ],
            [
                'code' => 'DESIG-00004',
                'name' => 'Store Manager',
                'default_grade_code' => 'GRADE-00004',
            ],
            [
                'code' => 'DESIG-00005',
                'name' => 'HR Officer',
                'default_grade_code' => 'GRADE-00002',
            ],
            [
                'code' => 'DESIG-00006',
                'name' => 'Accountant',
                'default_grade_code' => 'GRADE-00002',
            ],
            [
                'code' => 'DESIG-00007',
                'name' => 'IT Specialist',
                'default_grade_code' => 'GRADE-00002',
            ],
        ];

        foreach ($designations as $designation) {
            $defaultGradeId = $gradesByCode->get($designation['default_grade_code'])?->id;

            Designation::query()->updateOrCreate(
                [
                    'legal_entity_id' => $entity->id,
                    'code' => $designation['code'],
                ],
                [
                    'name' => $designation['name'],
                    'default_grade_id' => $defaultGradeId,
                    'status' => 'active',
                ],
            );
        }

        $this->syncSequence('designation', 'DESIG', count($designations) + 1);
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
