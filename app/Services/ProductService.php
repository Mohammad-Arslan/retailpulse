<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Product\CreateProductData;
use App\DTOs\Product\UpdateProductData;
use App\Enums\ProductType;
use App\Models\BranchProductPrice;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\UniqueSlug;
use App\Support\VariantMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductIdentifierService $identifiers,
        private readonly ImageService $images,
    ) {}

    public function create(CreateProductData $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = new Product(['name' => $data->name]);

            $product = $this->products->create([
                'type' => $data->type,
                'name' => $data->name,
                'slug' => UniqueSlug::forModel($product, $data->name),
                'description' => $data->description,
                'category_id' => $data->categoryId,
                'brand_id' => $data->brandId,
                'unit_id' => $data->unitId,
                'variant_attributes' => $data->type === ProductType::Variable
                    ? $data->variantAttributes
                    : null,
                'track_batches' => $data->trackBatches,
                'track_serials' => $data->type->tracksSerials(),
                'is_active' => $data->isActive,
            ]);

            $variants = $this->buildVariantsForCreate($product, $data);

            foreach ($variants as $index => $variantData) {
                $variant = $product->variants()->create([
                    ...$variantData,
                    'sort_order' => $index,
                    'is_default' => $index === 0,
                ]);

                if ($data->type === ProductType::Combo && $index === 0) {
                    $this->syncBundleItems($variant, $data->bundleItems);
                }
            }

            $defaultVariant = $product->variants()->where('is_default', true)->first()
                ?? $product->variants()->first();

            if ($defaultVariant !== null) {
                $this->syncBranchPrices($defaultVariant, $data->branchPrices);
            }

            if ($data->images !== []) {
                $this->images->attachMany($product, $data->images);
            }

            return $this->products->findByIdWithRelations($product->id) ?? $product;
        });
    }

    public function update(Product $product, UpdateProductData $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $slug = $product->name !== $data->name
                ? UniqueSlug::forModel($product, $data->name)
                : $product->slug;

            $product = $this->products->update($product, [
                'name' => $data->name,
                'slug' => $slug,
                'description' => $data->description,
                'category_id' => $data->categoryId,
                'brand_id' => $data->brandId,
                'unit_id' => $data->unitId,
                'variant_attributes' => $product->type === ProductType::Variable
                    ? $data->variantAttributes
                    : $product->variant_attributes,
                'track_batches' => $data->trackBatches,
                'is_active' => $data->isActive,
            ]);

            if ($product->type === ProductType::Variable && $data->regenerateVariants) {
                $product->variants()->delete();
                $product->update(['variant_attributes' => $data->variantAttributes]);
                $this->createVariableVariants($product, $data->variantAttributes, $data->variants);
            } elseif ($product->type === ProductType::Variable) {
                $this->syncVariableVariants($product, $data->variants);
            } else {
                $this->syncVariants($product, $data->variants);
            }

            if ($product->type === ProductType::Combo) {
                $parent = $product->variants()->where('is_default', true)->first()
                    ?? $product->variants()->first();

                if ($parent !== null) {
                    $this->syncBundleItems($parent, $data->bundleItems);
                }
            }

            $defaultVariant = $product->variants()->where('is_default', true)->first()
                ?? $product->variants()->first();

            if ($defaultVariant !== null) {
                $this->syncBranchPrices($defaultVariant, $data->branchPrices);
            }

            return $this->products->findByIdWithRelations($product->id) ?? $product;
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $this->images->purgeFor($product);
            $this->products->delete($product);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildVariantsForCreate(Product $product, CreateProductData $data): array
    {
        return match ($product->type) {
            ProductType::Variable => $this->variantPayloadsFromMatrix(
                $data->variantAttributes,
                $data->variants,
                $data->defaultCostPrice,
                $data->defaultSellPrice,
            ),
            ProductType::Combo => [[
                'name' => $product->name,
                'sku' => $this->identifiers->nextSku(),
                'barcode' => $this->identifiers->nextBarcode(),
                'cost_price' => $data->defaultCostPrice,
                'sell_price' => $data->defaultSellPrice,
                'reorder_point' => $this->resolveReorderPoint($data->variants[0] ?? [], $data->defaultReorderPoint),
                'attributes' => null,
            ]],
            default => [[
                'name' => $data->variants[0]['name'] ?? $product->name,
                'sku' => $data->variants[0]['sku'] ?? $this->identifiers->nextSku(),
                'barcode' => $this->resolveBarcode($data->variants[0]['barcode'] ?? null),
                'cost_price' => $data->variants[0]['cost_price'] ?? $data->defaultCostPrice,
                'sell_price' => $data->variants[0]['sell_price'] ?? $data->defaultSellPrice,
                'reorder_point' => $this->resolveReorderPoint($data->variants[0] ?? [], $data->defaultReorderPoint),
                'attributes' => $data->variants[0]['attributes'] ?? null,
            ]],
        };
    }

    /**
     * @param  list<array{name: string, options: list<string>}>  $attributeSets
     * @param  list<array<string, mixed>>  $variantOverrides
     * @return list<array<string, mixed>>
     */
    private function variantPayloadsFromMatrix(
        array $attributeSets,
        array $variantOverrides,
        float $defaultCost,
        float $defaultSell,
    ): array {
        $combinations = VariantMatrix::combinations($attributeSets);

        if ($combinations === []) {
            throw ValidationException::withMessages([
                'variant_attributes' => __('Add at least one attribute with options for variable products.'),
            ]);
        }

        return collect($combinations)->map(function (array $attributes, int $index) use ($variantOverrides, $defaultCost, $defaultSell) {
            $override = $variantOverrides[$index] ?? [];

            return [
                'name' => $override['name'] ?? VariantMatrix::label($attributes),
                'sku' => $override['sku'] ?? $this->identifiers->nextSku(),
                'barcode' => $this->resolveBarcode($override['barcode'] ?? null),
                'cost_price' => $override['cost_price'] ?? $defaultCost,
                'sell_price' => $override['sell_price'] ?? $defaultSell,
                'reorder_point' => $this->resolveReorderPoint($override, null),
                'attributes' => $attributes,
            ];
        })->all();
    }

    /**
     * @param  list<array{name: string, options: list<string>}>  $attributeSets
     * @param  list<array<string, mixed>>  $variantOverrides
     */
    private function createVariableVariants(Product $product, array $attributeSets, array $variantOverrides): void
    {
        $payloads = $this->variantPayloadsFromMatrix(
            $attributeSets,
            $variantOverrides,
            (float) ($variantOverrides[0]['cost_price'] ?? 0),
            (float) ($variantOverrides[0]['sell_price'] ?? 0),
        );

        foreach ($payloads as $index => $payload) {
            $product->variants()->create([
                ...$payload,
                'sort_order' => $index,
                'is_default' => $index === 0,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariableVariants(Product $product, array $variants): void
    {
        foreach ($variants as $row) {
            if (empty($row['id'])) {
                continue;
            }

            $product->variants()->whereKey($row['id'])->update([
                'name' => $row['name'] ?? null,
                'cost_price' => $row['cost_price'] ?? 0,
                'sell_price' => $row['sell_price'] ?? 0,
                'reorder_point' => $this->resolveReorderPoint($row, null),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        if ($product->type === ProductType::Combo) {
            return;
        }

        $existingIds = $product->variants()->pluck('id')->all();
        $keptIds = [];

        foreach ($variants as $index => $row) {
            $payload = [
                'name' => $row['name'] ?? $product->name,
                'sku' => $row['sku'] ?? $this->identifiers->nextSku(),
                'barcode' => $this->resolveBarcode($row['barcode'] ?? null),
                'cost_price' => $row['cost_price'] ?? 0,
                'sell_price' => $row['sell_price'] ?? 0,
                'reorder_point' => $this->resolveReorderPoint($row, null),
                'attributes' => $row['attributes'] ?? null,
                'sort_order' => $index,
                'is_default' => $index === 0,
            ];

            if (! empty($row['id'])) {
                /** @var ProductVariant|null $variant */
                $variant = $product->variants()->whereKey($row['id'])->first();

                if ($variant !== null) {
                    $variant->update($payload);
                    $keptIds[] = $variant->id;

                    continue;
                }
            }

            $created = $product->variants()->create($payload);
            $keptIds[] = $created->id;
        }

        $removeIds = array_diff($existingIds, $keptIds);

        if ($removeIds !== []) {
            $product->variants()->whereIn('id', $removeIds)->delete();
        }
    }

    /**
     * @param  list<array{child_variant_id: int, quantity: float}>  $items
     */
    private function syncBundleItems(ProductVariant $parent, array $items): void
    {
        $parent->bundleItems()->delete();

        foreach ($items as $item) {
            if ($item['child_variant_id'] === $parent->id) {
                throw ValidationException::withMessages([
                    'bundle_items' => __('A bundle cannot include itself.'),
                ]);
            }

            ProductBundleItem::query()->create([
                'parent_variant_id' => $parent->id,
                'child_variant_id' => $item['child_variant_id'],
                'quantity' => $item['quantity'],
            ]);
        }
    }

    /**
     * @param  list<array{branch_id: int, sell_price: float}>  $prices
     */
    private function resolveBarcode(?string $barcode): string
    {
        $barcode = $barcode !== null ? trim($barcode) : '';

        return $barcode !== '' ? $barcode : $this->identifiers->nextBarcode();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveReorderPoint(array $row, ?int $default): ?int
    {
        if (array_key_exists('reorder_point', $row)) {
            $value = $row['reorder_point'];

            if ($value === null || $value === '') {
                return null;
            }

            return (int) $value;
        }

        return $default;
    }

    private function syncBranchPrices(ProductVariant $variant, array $prices): void
    {
        $variant->branchPrices()->delete();

        foreach ($prices as $price) {
            if ($price['sell_price'] <= 0) {
                continue;
            }

            BranchProductPrice::query()->create([
                'branch_id' => $price['branch_id'],
                'product_variant_id' => $variant->id,
                'sell_price' => $price['sell_price'],
            ]);
        }
    }
}
