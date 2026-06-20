<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Support\TaggedCache;
use App\Support\TenantImportScope;
use App\Support\UniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 128],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'category_slug',
                'label' => 'Category Slug',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 128],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'brand_code',
                'label' => 'Brand Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 128],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'brand_slug',
                'label' => 'Brand Slug',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 128],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
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
            [
                'key' => 'preferred_supplier_code',
                'label' => 'Preferred Supplier Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 64],
                ],
                'default_transforms' => ['trim', 'uppercase', 'nullify_empty'],
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

        $categoryReference = $this->categoryReference($row);

        if ($categoryReference === '') {
            $errors['category_code'] = ['Category code or slug is required.'];
        } elseif ($this->findCategory($categoryReference, $context->tenantId) === null) {
            $errors['category_code'] = ['Category not found.'];
        }

        $brandReference = $this->brandReference($row);

        if ($brandReference !== '' && $this->findBrand($brandReference, $context->tenantId) === null) {
            $errors['brand_code'] = ['Brand not found.'];
        }

        $supplierCode = trim((string) ($row['preferred_supplier_code'] ?? ''));

        if ($supplierCode !== '' && $this->findSupplier($supplierCode, $context->tenantId) === null) {
            $errors['preferred_supplier_code'] = ['Supplier not found.'];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        return DB::transaction(function () use ($row, $context) {
            $category = $this->findCategory($this->categoryReference($row), $context->tenantId);

            if ($category === null) {
                return ImportRowResult::failure('Category not found.');
            }

            $brandId = null;
            $brandReference = $this->brandReference($row);

            if ($brandReference !== '') {
                $brand = $this->findBrand($brandReference, $context->tenantId);

                if ($brand === null) {
                    return ImportRowResult::failure('Brand not found.');
                }

                $brandId = $brand->id;
            }

            $unitId = null;
            if (! empty($row['unit_name'])) {
                $unit = TenantImportScope::constrain(Unit::query(), $context->tenantId)
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
                ...$this->resolveSupplierFields($row, $context->tenantId),
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
        TaggedCache::flush(['products:tenant:'.TenantImportScope::cacheKeySuffix($context->tenantId)]);
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
            ->whereHas('product', fn ($q) => TenantImportScope::constrain($q, $context->tenantId))
            ->with('product')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function categoryReference(array $row): string
    {
        foreach (['category_slug', 'category_code'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function brandReference(array $row): string
    {
        foreach (['brand_slug', 'brand_code'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function findCategory(string $reference, ?int $tenantId): ?Category
    {
        return $this->findTenantScopedRecord(Category::query(), $reference, $tenantId);
    }

    private function findBrand(string $reference, ?int $tenantId): ?Brand
    {
        return $this->findTenantScopedRecord(Brand::query(), $reference, $tenantId);
    }

    private function findSupplier(string $code, ?int $tenantId): ?Supplier
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        return TenantImportScope::constrain(Supplier::query(), $tenantId)
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper($code)])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{preferred_supplier_id: ?int, alternate_supplier_ids: null}
     */
    private function resolveSupplierFields(array $row, ?int $tenantId): array
    {
        $code = trim((string) ($row['preferred_supplier_code'] ?? ''));

        if ($code === '') {
            return [
                'preferred_supplier_id' => null,
                'alternate_supplier_ids' => null,
            ];
        }

        $supplier = $this->findSupplier($code, $tenantId);

        if ($supplier === null) {
            return [
                'preferred_supplier_id' => null,
                'alternate_supplier_ids' => null,
            ];
        }

        return [
            'preferred_supplier_id' => $supplier->id,
            'alternate_supplier_ids' => null,
        ];
    }

    /**
     * @param  Builder<Category|Brand>  $query
     */
    private function findTenantScopedRecord(Builder $query, string $reference, ?int $tenantId): Category|Brand|null
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        $slugCandidate = Str::slug($reference);
        $referenceLower = mb_strtolower($reference);
        $slugCandidateLower = mb_strtolower($slugCandidate);

        return TenantImportScope::constrain($query, $tenantId)
            ->where(function (Builder $scopedQuery) use ($referenceLower, $slugCandidateLower): void {
                $scopedQuery->whereRaw('LOWER(slug) = ?', [$referenceLower]);

                if ($slugCandidateLower !== '' && $slugCandidateLower !== $referenceLower) {
                    $scopedQuery->orWhereRaw('LOWER(slug) = ?', [$slugCandidateLower]);
                }

                $scopedQuery->orWhereRaw('LOWER(name) = ?', [$referenceLower]);
            })
            ->first();
    }
}
