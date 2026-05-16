<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Category;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

final class CategoryImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            [
                'key' => 'code',
                'label' => 'Category Code',
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
                'key' => 'parent_code',
                'label' => 'Parent Category Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'exists_in_db', 'table' => 'categories', 'column' => 'slug', 'scope' => 'tenant'],
                ],
                'default_transforms' => ['trim', 'slug', 'nullify_empty'],
            ],
            [
                'key' => 'description',
                'label' => 'Description',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 2000]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'sort_order',
                'label' => 'Sort Order',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'numeric', 'min' => 0]],
                'default_transforms' => ['cast_int'],
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
        $exists = Category::query()
            ->where('tenant_id', $context->tenantId)
            ->where('slug', $code)
            ->exists();

        if ($context->mode === 'create' && $exists) {
            $errors['code'] = ['Category code already exists.'];
        }

        if ($context->mode === 'update' && ! $exists) {
            $errors['code'] = ['Category not found for update.'];
        }

        if ($code !== '' && ($row['parent_code'] ?? '') === $code) {
            $errors['parent_code'] = ['A category cannot be its own parent.'];
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
            $category = Category::query()
                ->where('tenant_id', $context->tenantId)
                ->where('slug', $code)
                ->first();

            $parentId = null;
            if (! empty($row['parent_code'])) {
                $parent = Category::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('slug', (string) $row['parent_code'])
                    ->first();

                if ($parent === null) {
                    return ImportRowResult::failure('Parent category not found.');
                }

                $parentId = $parent->id;
            }

            $attributes = [
                'name' => (string) ($row['name'] ?? ''),
                'parent_id' => $parentId,
                'description' => $row['description'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => array_key_exists('is_active', $row)
                    ? (bool) $row['is_active']
                    : true,
            ];

            if ($category !== null) {
                if ($category->name !== $attributes['name']) {
                    $attributes['slug'] = UniqueSlug::forModel($category, $attributes['name']);
                }
                $category->update($attributes);

                return ImportRowResult::success($category->id);
            }

            if ($context->mode === 'update') {
                return ImportRowResult::failure('Category not found for update.');
            }

            $model = new Category(['name' => $attributes['name']]);
            $category = Category::query()->create([
                ...$attributes,
                'tenant_id' => $context->tenantId,
                'slug' => $code !== '' ? $code : UniqueSlug::forModel($model, $attributes['name']),
            ]);

            return ImportRowResult::success($category->id);
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
