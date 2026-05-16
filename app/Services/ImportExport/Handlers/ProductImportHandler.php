<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ProductImportHandler implements ImportHandler
{
    public function columns(): array
    {
        return [
            [
                'key' => 'name',
                'label' => 'Product Name',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'min' => 1, 'max' => 255]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'sku',
                'label' => 'SKU',
                'required' => true,
                'default_rules' => [
                    ['rule' => 'required'],
                    ['rule' => 'regex', 'pattern' => '/^[A-Z0-9-]{3,32}$/'],
                ],
                'default_transforms' => ['trim', 'uppercase'],
            ],
            [
                'key' => 'category_code',
                'label' => 'Category Code',
                'required' => true,
                'default_rules' => [
                    ['rule' => 'required'],
                    ['rule' => 'exists_in_db', 'table' => 'categories', 'column' => 'slug', 'scope' => 'tenant'],
                ],
                'default_transforms' => ['trim', 'slug'],
            ],
            [
                'key' => 'brand_code',
                'label' => 'Brand Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'exists_in_db', 'table' => 'brands', 'column' => 'slug', 'scope' => 'tenant'],
                ],
                'default_transforms' => ['trim', 'slug', 'nullify_empty'],
            ],
            [
                'key' => 'unit_name',
                'label' => 'Unit Name',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'exists_in_db', 'table' => 'units', 'column' => 'name', 'scope' => 'tenant'],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'barcode',
                'label' => 'Barcode',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'cost_price',
                'label' => 'Cost Price',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'numeric', 'min' => 0]],
                'default_transforms' => ['cast_float'],
            ],
            [
                'key' => 'sell_price',
                'label' => 'Sell Price',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'numeric', 'min' => 0]],
                'default_transforms' => ['cast_float'],
            ],
            [
                'key' => 'type',
                'label' => 'Product Type',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    [
                        'rule' => 'in_list',
                        'values' => ['standard', 'variable', 'service', 'digital', 'serialized', 'combo'],
                    ],
                ],
                'default_transforms' => ['trim', 'lowercase'],
            ],
            [
                'key' => 'variant_label',
                'label' => 'Variant Label',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 128]],
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
        $matchField = $this->matchField($context);
        $variant = $this->findVariant($row, $context, $matchField);

        if ($context->mode === 'create' && $variant !== null) {
            $errors[$matchField] = [ucfirst($matchField).' already exists for this tenant.'];
        }

        if ($context->mode === 'update' && $variant === null) {
            $errors[$matchField] = ['Product variant not found for update.'];
        }

        if ($context->mode === 'upsert' && $matchField === 'barcode' && empty($row['barcode'])) {
            $errors['barcode'] = ['Barcode is required when matching by barcode.'];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row, $context) {
            $category = Category::query()
                ->where('tenant_id', $context->tenantId)
                ->where('slug', (string) ($row['category_code'] ?? ''))
                ->first();

            if ($category === null) {
                return ImportRowResult::failure('Category not found.');
            }

            $brandId = null;
            if (! empty($row['brand_code'])) {
                $brand = Brand::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('slug', (string) $row['brand_code'])
                    ->first();

                if ($brand === null) {
                    return ImportRowResult::failure('Brand not found.');
                }

                $brandId = $brand->id;
            }

            $unitId = null;
            if (! empty($row['unit_name'])) {
                $unit = Unit::query()
                    ->where('tenant_id', $context->tenantId)
                    ->where('name', (string) $row['unit_name'])
                    ->first();

                if ($unit === null) {
                    return ImportRowResult::failure('Unit not found.');
                }

                $unitId = $unit->id;
            }

            $type = ProductType::tryFrom((string) ($row['type'] ?? 'standard'))
                ?? ProductType::Standard;

            $matchField = $this->matchField($context);
            $variant = $this->findVariant($row, $context, $matchField);

            if ($context->mode === 'update' && $variant === null) {
                return ImportRowResult::failure('Product variant not found for update.');
            }

            if ($context->mode === 'create' && $variant !== null) {
                return ImportRowResult::failure(ucfirst($matchField).' already exists.');
            }

            $productAttributes = [
                'name' => (string) ($row['name'] ?? ''),
                'category_id' => $category->id,
                'brand_id' => $brandId,
                'unit_id' => $unitId,
                'type' => $type,
                'is_active' => array_key_exists('is_active', $row)
                    ? (bool) $row['is_active']
                    : true,
            ];

            if ($variant !== null) {
                $product = $variant->product;
                $product->update($productAttributes);
            } else {
                $product = new Product(['name' => $productAttributes['name']]);
                $product = Product::query()->create([
                    ...$productAttributes,
                    'tenant_id' => $context->tenantId,
                    'slug' => UniqueSlug::forModel($product, $productAttributes['name']),
                ]);
                $variant = null;
            }

            $variantAttributes = [
                'name' => $row['variant_label'] ?? null,
                'sku' => (string) ($row['sku'] ?? ''),
                'barcode' => $row['barcode'] ?? null,
                'cost_price' => $row['cost_price'] ?? 0,
                'sell_price' => $row['sell_price'] ?? 0,
            ];

            if ($variant === null) {
                $product->variants()->create([
                    ...$variantAttributes,
                    'is_default' => true,
                    'sort_order' => 0,
                ]);
            } else {
                $variant->update($variantAttributes);
            }

            return ImportRowResult::success($product->id);
        });
    }

    public function afterImport(ImportContext $context): void
    {
        Cache::tags(["products:tenant:{$context->tenantId}"])->flush();
    }

    public function chunkSize(): int
    {
        return 200;
    }

    private function matchField(ImportContext $context): string
    {
        $field = (string) ($context->options['match_field'] ?? 'sku');

        return in_array($field, ['sku', 'barcode'], true) ? $field : 'sku';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function findVariant(array $row, ImportContext $context, string $matchField): ?ProductVariant
    {
        $value = (string) ($row[$matchField] ?? '');

        if ($value === '') {
            return null;
        }

        return ProductVariant::query()
            ->where($matchField, $value)
            ->whereHas('product', fn ($q) => $q->where('tenant_id', $context->tenantId))
            ->with('product')
            ->first();
    }
}
