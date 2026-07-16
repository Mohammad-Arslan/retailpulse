<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Grade;
use App\Models\OrganizationEntity;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use Illuminate\Support\Facades\DB;

final class GradeImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Grade Code', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 64]], 'default_transforms' => ['trim', 'uppercase']],
            ['key' => 'name', 'label' => 'Name', 'required' => true, 'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim']],
            ['key' => 'legal_entity', 'label' => 'Legal Entity', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 255]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'rank', 'label' => 'Rank', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'numeric']], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'currency_code', 'label' => 'Currency Code', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'size' => 3]], 'default_transforms' => ['trim', 'uppercase', 'nullify_empty']],
            ['key' => 'min_amount', 'label' => 'Min Amount', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'decimal', 'min' => 0]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'mid_amount', 'label' => 'Mid Amount', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'decimal', 'min' => 0]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'max_amount', 'label' => 'Max Amount', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'decimal', 'min' => 0]], 'default_transforms' => ['trim', 'nullify_empty']],
            ['key' => 'effective_from', 'label' => 'Effective From', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']], 'default_transforms' => ['trim', 'date_normalize', 'nullify_empty']],
            ['key' => 'effective_to', 'label' => 'Effective To', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']], 'default_transforms' => ['trim', 'date_normalize', 'nullify_empty']],
            ['key' => 'status', 'label' => 'Status', 'required' => false, 'default_rules' => [['rule' => 'nullable'], ['rule' => 'in_list', 'values' => ['active', 'inactive']]], 'default_transforms' => ['trim', 'lowercase', 'nullify_empty']],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $code = (string) ($row['code'] ?? '');
        $exists = Grade::query()->where('code', $code)->exists();
        if ($context->mode === 'create' && $exists) {
            return ['code' => ['Grade code already exists.']];
        }
        if ($context->mode === 'update' && ! $exists) {
            return ['code' => ['Grade not found for update.']];
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
            $grade = Grade::query()->where('code', $code)->first();
            $entityId = null;
            if (! empty($row['legal_entity'])) {
                $entityId = OrganizationEntity::query()
                    ->where('legal_name', (string) $row['legal_entity'])
                    ->orWhere('tax_registration_no', (string) $row['legal_entity'])
                    ->value('id');
            }

            $attributes = [
                'legal_entity_id' => $entityId,
                'name' => (string) ($row['name'] ?? ''),
                'rank' => isset($row['rank']) && $row['rank'] !== '' ? (int) $row['rank'] : 0,
                'currency_code' => $row['currency_code'] ?? null,
                'min_amount' => $row['min_amount'] ?? null,
                'mid_amount' => $row['mid_amount'] ?? null,
                'max_amount' => $row['max_amount'] ?? null,
                'effective_from' => $row['effective_from'] ?? null,
                'effective_to' => $row['effective_to'] ?? null,
                'status' => $row['status'] ?? 'active',
            ];

            if ($grade !== null) {
                $grade->update($attributes);

                return ImportRowResult::success($grade->id);
            }
            if ($context->mode === 'update') {
                return ImportRowResult::failure('Grade not found for update.');
            }

            $created = Grade::query()->create([...$attributes, 'code' => $code]);

            return ImportRowResult::success($created->id);
        });
    }

    public function afterImport(ImportContext $context): void {}

    public function chunkSize(): int
    {
        return 200;
    }
}
