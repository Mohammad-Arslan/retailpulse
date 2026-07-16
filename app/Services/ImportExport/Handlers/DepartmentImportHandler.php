<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\CostCentre;
use App\Models\Department;
use App\Models\OrganizationEntity;
use App\Services\Hr\DepartmentService;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Support\Facades\DB;

final class DepartmentImportHandler implements ImportHandler
{
    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Department Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'uppercase']],
            ['key' => 'name', 'label' => 'Name', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'legal_entity', 'label' => 'Legal Entity', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'parent_code', 'label' => 'Parent Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'uppercase', 'nullify_empty']],
            ['key' => 'cost_centre_code', 'label' => 'Cost Centre Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'status', 'label' => 'Status', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'in_list', 'values' => ['active', 'inactive']]], 'default_transforms' => ['trim', 'lowercase', 'nullify_empty']],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $errors = [];
        $code = (string) ($row['code'] ?? '');
        $entityId = $this->resolveLegalEntityId((string) ($row['legal_entity'] ?? ''));
        if ($entityId === null) {
            $errors['legal_entity'] = ['Legal entity not found.'];
        }

        $exists = $entityId !== null && Department::query()
            ->where('legal_entity_id', $entityId)
            ->where('code', $code)
            ->exists();

        if ($context->mode === 'create' && $exists) {
            $errors['code'] = ['Department code already exists for this entity.'];
        }
        if (in_array($context->mode, ['update', 'upsert'], true) && ! $exists) {
            $errors['code'] = ['Department not found for update.'];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row, $context) {
            $entityId = $this->resolveLegalEntityId((string) ($row['legal_entity'] ?? ''));
            if ($entityId === null) {
                return ImportRowResult::failure('Legal entity not found.');
            }

            $code = (string) ($row['code'] ?? '');
            $department = Department::query()
                ->where('legal_entity_id', $entityId)
                ->where('code', $code)
                ->first();

            $parentId = null;
            if (! empty($row['parent_code'])) {
                $parentId = Department::query()
                    ->where('legal_entity_id', $entityId)
                    ->where('code', (string) $row['parent_code'])
                    ->value('id');
                if ($parentId === null) {
                    return ImportRowResult::failure('Parent department not found.');
                }
            }

            $costCentreId = null;
            if (! empty($row['cost_centre_code'])) {
                $costCentreId = CostCentre::query()->where('code', (string) $row['cost_centre_code'])->value('id');
            }

            $attributes = [
                'legal_entity_id' => $entityId,
                'name' => (string) ($row['name'] ?? ''),
                'parent_id' => $parentId,
                'cost_centre_id' => $costCentreId,
                'status' => $row['status'] ?? 'active',
            ];

            if ($department !== null) {
                if ($attributes['parent_id'] !== null) {
                    $this->departments->assertNoCycle($department->id, (int) $attributes['parent_id']);
                }
                $department->update($attributes);

                return ImportRowResult::success($department->id);
            }

            if ($context->mode === 'update') {
                return ImportRowResult::failure('Department not found for update.');
            }

            if ($attributes['parent_id'] !== null) {
                $this->departments->assertNoCycle(null, (int) $attributes['parent_id']);
            }

            $created = Department::query()->create([...$attributes, 'code' => $code]);

            return ImportRowResult::success($created->id);
        });
    }

    public function afterImport(ImportContext $context): void {}

    public function chunkSize(): int
    {
        return 200;
    }

    private function resolveLegalEntityId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        return OrganizationEntity::query()
            ->where(function ($q) use ($value): void {
                $q->where('legal_name', $value)->orWhere('tax_registration_no', $value);
            })
            ->value('id');
    }
}
