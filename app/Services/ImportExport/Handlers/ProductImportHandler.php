<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
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
                'key' => 'barcode',
                'label' => 'Barcode',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]],
                'default_transforms' => ['trim'],
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
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $errors = [];

        if ($context->mode === 'create') {
            $exists = ProductVariant::query()
                ->where('sku', $row['sku'] ?? '')
                ->whereHas('product', fn ($q) => $q->where('tenant_id', $context->tenantId))
                ->exists();

            if ($exists) {
                $errors['sku'] = ['SKU already exists for this tenant.'];
            }
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
                ->where('slug', $row['category_code'] ?? '')
                ->first();

            if ($category === null) {
                return ImportRowResult::failure('Category not found.');
            }

            $type = ProductType::tryFrom((string) ($row['type'] ?? 'standard'))
                ?? ProductType::Standard;

            $variant = ProductVariant::query()
                ->where('sku', $row['sku'])
                ->whereHas('product', fn ($q) => $q->where('tenant_id', $context->tenantId))
                ->with('product')
                ->first();

            if ($variant !== null) {
                $product = $variant->product;
                $product->update([
                    'name' => $row['name'],
                    'category_id' => $category->id,
                    'type' => $type,
                ]);
            } else {
                $product = new Product(['name' => (string) $row['name']]);
                $product = Product::query()->create([
                    'tenant_id' => $context->tenantId,
                    'category_id' => $category->id,
                    'type' => $type,
                    'name' => $row['name'],
                    'slug' => UniqueSlug::forModel($product, (string) $row['name']),
                    'is_active' => true,
                ]);
                $variant = null;
            }

            if ($variant === null) {
                $variant = $product->variants()->create([
                    'name' => null,
                    'sku' => $row['sku'],
                    'barcode' => $row['barcode'] ?? null,
                    'cost_price' => $row['cost_price'] ?? 0,
                    'sell_price' => $row['sell_price'] ?? 0,
                    'is_default' => true,
                    'sort_order' => 0,
                ]);
            } else {
                $variant->update([
                    'barcode' => $row['barcode'] ?? $variant->barcode,
                    'cost_price' => $row['cost_price'] ?? $variant->cost_price,
                    'sell_price' => $row['sell_price'] ?? $variant->sell_price,
                ]);
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
}
