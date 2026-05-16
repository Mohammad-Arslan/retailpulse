<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Brand;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

final class BrandImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            [
                'key' => 'code',
                'label' => 'Brand Code',
                'required' => true,
                'default_rules' => [
                    ['rule' => 'required'],
                    ['rule' => 'string', 'min' => 1, 'max' => 128],
                    ['rule' => 'regex', 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                ],
                'default_transforms' => ['trim', 'slug'],
            ],
            [
                'key' => 'name',
                'label' => 'Name',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'min' => 1, 'max' => 255]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'description',
                'label' => 'Description',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 2000]],
                'default_transforms' => ['trim', 'nullify_empty'],
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
        $code = (string) ($row['code'] ?? '');
        $exists = Brand::query()
            ->where('tenant_id', $context->tenantId)
            ->where('slug', $code)
            ->exists();

        if ($context->mode === 'create' && $exists) {
            $errors['code'] = ['Brand code already exists.'];
        }

        if ($context->mode === 'update' && ! $exists) {
            $errors['code'] = ['Brand not found for update.'];
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
            $brand = Brand::query()
                ->where('tenant_id', $context->tenantId)
                ->where('slug', $code)
                ->first();

            $attributes = [
                'name' => (string) ($row['name'] ?? ''),
                'description' => $row['description'] ?? null,
                'is_active' => array_key_exists('is_active', $row)
                    ? (bool) $row['is_active']
                    : true,
            ];

            if ($brand !== null) {
                if ($brand->name !== $attributes['name']) {
                    $attributes['slug'] = UniqueSlug::forModel($brand, $attributes['name']);
                }
                $brand->update($attributes);

                return ImportRowResult::success($brand->id);
            }

            if ($context->mode === 'update') {
                return ImportRowResult::failure('Brand not found for update.');
            }

            $model = new Brand(['name' => $attributes['name']]);
            $brand = Brand::query()->create([
                ...$attributes,
                'tenant_id' => $context->tenantId,
                'slug' => $code !== '' ? $code : UniqueSlug::forModel($model, $attributes['name']),
            ]);

            return ImportRowResult::success($brand->id);
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
