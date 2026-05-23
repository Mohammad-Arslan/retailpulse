<?php

declare(strict_types=1);

namespace App\DTOs\Product;

use App\Http\Requests\Admin\UpdateProductRequest;

final readonly class UpdateProductData
{
    /**
     * @param  list<array{name: string, options: list<string>}>  $variantAttributes
     * @param  list<array{id?: int|null, sku?: string|null, barcode?: string|null, name?: string|null, cost_price: float, sell_price: float, attributes?: array<string, string>|null, is_default?: bool}>  $variants
     * @param  list<array{child_variant_id: int, quantity: float}>  $bundleItems
     * @param  list<array{branch_id: int, sell_price: float}>  $branchPrices
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?int $categoryId,
        public ?int $brandId,
        public ?int $unitId,
        public bool $trackBatches,
        public bool $isActive,
        public array $variantAttributes,
        public array $variants,
        public array $bundleItems,
        public array $branchPrices,
        public bool $regenerateVariants,
    ) {}

    public static function fromRequest(UpdateProductRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            categoryId: self::nullableInt($request->validated('category_id')),
            brandId: self::nullableInt($request->validated('brand_id')),
            unitId: self::nullableInt($request->validated('unit_id')),
            trackBatches: $request->boolean('track_batches'),
            isActive: $request->boolean('is_active', true),
            variantAttributes: $request->validated('variant_attributes', []),
            variants: self::normalizeVariants($request->validated('variants', [])),
            bundleItems: self::normalizeBundleItems($request->validated('bundle_items', [])),
            branchPrices: self::normalizeBranchPrices($request->validated('branch_prices', [])),
            regenerateVariants: $request->boolean('regenerate_variants'),
        );
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @return list<array<string, mixed>>
     */
    private static function normalizeVariants(array $variants): array
    {
        return array_map(static function (array $variant): array {
            if (isset($variant['id']) && $variant['id'] !== null && $variant['id'] !== '') {
                $variant['id'] = (int) $variant['id'];
            }

            return $variant;
        }, $variants);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{child_variant_id: int, quantity: float}>
     */
    private static function normalizeBundleItems(array $items): array
    {
        return array_map(
            static fn (array $item): array => [
                'child_variant_id' => (int) $item['child_variant_id'],
                'quantity' => (float) $item['quantity'],
            ],
            $items,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $prices
     * @return list<array{branch_id: int, sell_price: float}>
     */
    private static function normalizeBranchPrices(array $prices): array
    {
        return array_map(
            static fn (array $price): array => [
                'branch_id' => (int) $price['branch_id'],
                'sell_price' => (float) $price['sell_price'],
            ],
            $prices,
        );
    }
}
