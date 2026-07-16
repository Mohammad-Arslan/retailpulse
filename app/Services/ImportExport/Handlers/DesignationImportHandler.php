<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Designation;
use App\Models\Grade;
use App\Models\OrganizationEntity;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Support\Facades\DB;

final class DesignationImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Designation Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'uppercase']],
            ['key' => 'name', 'label' => 'Name', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'legal_entity', 'label' => 'Legal Entity', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'grade_code', 'label' => 'Default Grade Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'uppercase', 'nullify_empty']],
            ['key' => 'status', 'label' => 'Status', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'in_list', 'values' => ['active', 'inactive']]], 'default_transforms' => ['trim', 'lowercase', 'nullify_empty']],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $code = (string) ($row['code'] ?? '');
        $exists = Designation::query()->where('code', $code)->exists();
        if ($context->mode === 'create' && $exists) {
            return ['code' => ['Designation code already exists.']];
        }
        if ($context->mode === 'update' && ! $exists) {
            return ['code' => ['Designation not found for update.']];
        }

        return [];
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row, $context) {
            $code = (string) ($row['code'] ?? '');
            $designation = Designation::query()->where('code', $code)->first();
            $entityId = $this->resolveLegalEntityId($row['legal_entity'] ?? null);
            $gradeId = null;
            if (! empty($row['grade_code'])) {
                $gradeId = Grade::query()->where('code', (string) $row['grade_code'])->value('id');
            }

            $attributes = [
                'legal_entity_id' => $entityId,
                'name' => (string) ($row['name'] ?? ''),
                'default_grade_id' => $gradeId,
                'status' => $row['status'] ?? 'active',
            ];

            if ($designation !== null) {
                $designation->update($attributes);

                return ImportRowResult::success($designation->id);
            }
            if ($context->mode === 'update') {
                return ImportRowResult::failure('Designation not found for update.');
            }

            $created = Designation::query()->create([...$attributes, 'code' => $code]);

            return ImportRowResult::success($created->id);
        });
    }

    public function afterImport(ImportContext $context): void {}

    public function chunkSize(): int
    {
        return 200;
    }

    private function resolveLegalEntityId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return OrganizationEntity::query()
            ->where('legal_name', (string) $value)
            ->orWhere('tax_registration_no', (string) $value)
            ->value('id');
    }
}
