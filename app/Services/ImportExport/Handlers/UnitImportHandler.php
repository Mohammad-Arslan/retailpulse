<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Unit;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Support\TenantImportScope;
use Illuminate\Support\Facades\DB;

final class UnitImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            [
                'key' => 'name',
                'label' => 'Unit Name',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'min' => 1, 'max' => 64]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'abbreviation',
                'label' => 'Abbreviation',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 16]],
                'default_transforms' => ['trim', 'uppercase', 'nullify_empty'],
            ],
            [
                'key' => 'is_active',
                'label' => 'Active',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'boolean']],
                'default_transforms' => ['cast_bool'],
            ],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $errors = [];
        $name = (string) ($row['name'] ?? '');
        $exists = TenantImportScope::constrain(Unit::query(), $context->tenantId)
            ->where('name', $name)
            ->exists();

        if ($context->mode === 'create' && $exists) {
            $errors['name'] = ['Unit name already exists.'];
        }

        if ($context->mode === 'update' && ! $exists) {
            $errors['name'] = ['Unit not found for update.'];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row, $context) {
            $name = (string) ($row['name'] ?? '');
            $unit = TenantImportScope::constrain(Unit::query(), $context->tenantId)
                ->where('name', $name)
                ->first();

            $attributes = [
                'abbreviation' => $row['abbreviation'] ?? null,
                'is_active' => array_key_exists('is_active', $row)
                    ? (bool) $row['is_active']
                    : true,
            ];

            if ($unit !== null) {
                $unit->update($attributes);

                return ImportRowResult::success($unit->id);
            }

            if ($context->mode === 'update') {
                return ImportRowResult::failure('Unit not found for update.');
            }

            $unit = Unit::query()->create([
                ...$attributes,
                'tenant_id' => $context->tenantId,
                'name' => $name,
            ]);

            return ImportRowResult::success($unit->id);
        });
    }

    public function afterImport(ImportContext $context): void
    {
        //
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
