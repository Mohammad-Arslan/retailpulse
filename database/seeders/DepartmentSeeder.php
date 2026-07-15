<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\OrganizationEntity;
use Illuminate\Database\Seeder;

final class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $entity = OrganizationEntity::query()->where('status', 'active')->orderBy('id')->first();
        if ($entity === null) {
            return;
        }

        $roots = [
            ['code' => 'DEPT-00001', 'name' => 'Operations'],
            ['code' => 'DEPT-00002', 'name' => 'Human Resources'],
            ['code' => 'DEPT-00003', 'name' => 'Finance'],
            ['code' => 'DEPT-00004', 'name' => 'Information Technology'],
        ];

        $created = [];

        foreach ($roots as $department) {
            $created[$department['code']] = Department::query()->updateOrCreate(
                [
                    'legal_entity_id' => $entity->id,
                    'code' => $department['code'],
                ],
                [
                    'name' => $department['name'],
                    'parent_id' => null,
                    'cost_centre_id' => null,
                    'status' => 'active',
                ],
            );
        }

        $children = [
            [
                'code' => 'DEPT-00005',
                'name' => 'Store Operations',
                'parent_code' => 'DEPT-00001',
            ],
            [
                'code' => 'DEPT-00006',
                'name' => 'Sales',
                'parent_code' => 'DEPT-00005',
            ],
            [
                'code' => 'DEPT-00007',
                'name' => 'Warehouse',
                'parent_code' => 'DEPT-00001',
            ],
            [
                'code' => 'DEPT-00008',
                'name' => 'Recruitment',
                'parent_code' => 'DEPT-00002',
            ],
            [
                'code' => 'DEPT-00009',
                'name' => 'Accounts Payable',
                'parent_code' => 'DEPT-00003',
            ],
        ];

        foreach ($children as $department) {
            $parentId = $created[$department['parent_code']]->id ?? null;

            $created[$department['code']] = Department::query()->updateOrCreate(
                [
                    'legal_entity_id' => $entity->id,
                    'code' => $department['code'],
                ],
                [
                    'name' => $department['name'],
                    'parent_id' => $parentId,
                    'cost_centre_id' => null,
                    'status' => 'active',
                ],
            );
        }

        $this->syncSequence('department', 'DEPT', count($roots) + count($children) + 1);
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
