<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\HrEmploymentType;
use App\Models\OrganizationEntity;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Support\Facades\DB;

final class EmploymentTypeImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employment Type Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64], ['rule' => 'regex', 'pattern' => '/^[a-z0-9_]+$/']], 'default_transforms' => ['trim', 'lowercase']],
            ['key' => 'name', 'label' => 'Name', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'legal_entity', 'label' => 'Legal Entity', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'status', 'label' => 'Status', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'in_list', 'values' => ['active', 'inactive']]], 'default_transforms' => ['trim', 'lowercase', 'nullify_empty']],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $errors = [];
        $code = (string) ($row['code'] ?? '');
        $entityId = $this->resolveLegalEntityId($row['legal_entity'] ?? null);

        if (! empty($row['legal_entity']) && $entityId === null) {
            $errors['legal_entity'] = ['Legal entity not found.'];
        }

        $exists = $this->findExisting($code, $entityId) !== null;

        if ($context->mode === 'create' && $exists) {
            $errors['code'] = ['Employment type code already exists for this scope.'];
        }
        if (in_array($context->mode, ['update', 'upsert'], true) && ! $exists && $context->mode === 'update') {
            $errors['code'] = ['Employment type not found for update.'];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row, $context) {
            $code = (string) ($row['code'] ?? '');
            $entityId = $this->resolveLegalEntityId($row['legal_entity'] ?? null);

            if (! empty($row['legal_entity']) && $entityId === null) {
                return ImportRowResult::failure('Legal entity not found.');
            }

            $type = $this->findExisting($code, $entityId);
            $attributes = [
                'legal_entity_id' => $entityId,
                'name' => (string) ($row['name'] ?? ''),
                'status' => $row['status'] ?? 'active',
            ];

            if ($type !== null) {
                $type->update($attributes);

                return ImportRowResult::success($type->id);
            }

            if ($context->mode === 'update') {
                return ImportRowResult::failure('Employment type not found for update.');
            }

            $created = HrEmploymentType::query()->create([...$attributes, 'code' => $code]);

            return ImportRowResult::success($created->id);
        });
    }

    public function afterImport(ImportContext $context): void {}

    public function chunkSize(): int
    {
        return 200;
    }

    private function findExisting(string $code, ?int $entityId): ?HrEmploymentType
    {
        $query = HrEmploymentType::query()->where('code', $code);
        if ($entityId === null) {
            $query->whereNull('legal_entity_id');
        } else {
            $query->where('legal_entity_id', $entityId);
        }

        return $query->first();
    }

    private function resolveLegalEntityId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $label = (string) $value;

        return OrganizationEntity::query()
            ->where('legal_name', $label)
            ->orWhere('tax_registration_no', $label)
            ->value('id');
    }
}
