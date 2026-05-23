<?php

declare(strict_types=1);

namespace App\DTOs\Product;

use App\Enums\ProductType;
use App\Http\Requests\Admin\StoreProductRequest;
use Illuminate\Http\UploadedFile;

final readonly class CreateProductData
{
    /**
     * @param  list<array{name: string, options: list<string>}>  $variantAttributes
     * @param  list<array{sku?: string|null, barcode?: string|null, name?: string|null, cost_price: float, sell_price: float, attributes?: array<string, string>|null, is_default?: bool}>  $variants
     * @param  list<array{child_variant_id: int, quantity: float}>  $bundleItems
     * @param  list<array{branch_id: int, sell_price: float}>  $branchPrices
     * @param  list<UploadedFile>  $images
     */
    public function __construct(
        public ProductType $type,
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
        public float $defaultCostPrice,
        public float $defaultSellPrice,
        public ?int $defaultReorderPoint,
        public array $images,
    ) {}

    public static function fromRequest(StoreProductRequest $request): self
    {
        return new self(
            type: ProductType::from($request->validated('type')),
            name: $request->validated('name'),
            description: $request->validated('description'),
            categoryId: self::nullableInt($request->validated('category_id')),
            brandId: self::nullableInt($request->validated('brand_id')),
            unitId: self::nullableInt($request->validated('unit_id')),
            trackBatches: $request->boolean('track_batches'),
            isActive: $request->boolean('is_active', true),
            variantAttributes: $request->validated('variant_attributes', []),
            variants: $request->validated('variants', []),
            bundleItems: self::normalizeBundleItems($request->validated('bundle_items', [])),
            branchPrices: self::normalizeBranchPrices($request->validated('branch_prices', [])),
            defaultCostPrice: (float) $request->validated('default_cost_price', 0),
            defaultSellPrice: (float) $request->validated('default_sell_price', 0),
            defaultReorderPoint: self::nullableInt($request->validated('default_reorder_point')),
            images: self::normalizeUploadedFiles($request->file('images')),
        );
    }

    /**
     * @return list<UploadedFile>
     */
    private static function normalizeUploadedFiles(mixed $files): array
    {
        if ($files === null) {
            return [];
        }

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
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
