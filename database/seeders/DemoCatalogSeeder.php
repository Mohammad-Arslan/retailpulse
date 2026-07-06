<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\Branch;
use App\Models\BranchProductPrice;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Seeder;

final class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $unit = Unit::query()->where('abbreviation', 'ea')->first()
          ?? Unit::query()->where('abbreviation', 'pc')->first();

        if ($unit === null) {
            $this->command?->warn('DemoCatalogSeeder: no unit found — run UnitSeeder first.');

            return;
        }

        $supplier = Supplier::query()->where('code', 'SUP-ACME')->first();

        $beverages = Category::query()->firstOrCreate(
            ['slug' => 'beverages'],
            ['name' => 'Beverages', 'is_active' => true, 'sort_order' => 1],
        );

        $snacks = Category::query()->firstOrCreate(
            ['slug' => 'snacks'],
            ['name' => 'Snacks', 'is_active' => true, 'sort_order' => 2],
        );

        $electronics = Category::query()->firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics', 'is_active' => true, 'sort_order' => 3],
        );

        $dairy = Category::query()->firstOrCreate(
            ['slug' => 'dairy'],
            ['name' => 'Dairy', 'is_active' => true, 'sort_order' => 4],
        );

        $acmeBrand = Brand::query()->firstOrCreate(
            ['slug' => 'acme'],
            ['name' => 'Acme', 'is_active' => true],
        );

        $genericBrand = Brand::query()->firstOrCreate(
            ['slug' => 'store-brand'],
            ['name' => 'Store Brand', 'is_active' => true],
        );

        $catalog = [
            [
                'slug' => 'cola-330ml',
                'name' => 'Cola 330ml Can',
                'category_id' => $beverages->id,
                'brand_id' => $acmeBrand->id,
                'sku' => 'DEMO-BEV-001',
                'sell_price' => 1.50,
                'cost_price' => 0.85,
                'reorder_point' => 24,
                'track_batches' => false,
            ],
            [
                'slug' => 'potato-chips',
                'name' => 'Potato Chips 150g',
                'category_id' => $snacks->id,
                'brand_id' => $genericBrand->id,
                'sku' => 'DEMO-SNK-001',
                'sell_price' => 2.99,
                'cost_price' => 1.40,
                'reorder_point' => 15,
                'track_batches' => false,
            ],
            [
                'slug' => 'usb-c-cable',
                'name' => 'USB-C Cable 1m',
                'category_id' => $electronics->id,
                'brand_id' => $acmeBrand->id,
                'sku' => 'DEMO-ELC-001',
                'sell_price' => 12.99,
                'cost_price' => 6.50,
                'reorder_point' => 8,
                'track_batches' => false,
            ],
            [
                'slug' => 'organic-milk-1l',
                'name' => 'Organic Milk 1L',
                'category_id' => $dairy->id,
                'brand_id' => $genericBrand->id,
                'sku' => 'DEMO-DAI-001',
                'sell_price' => 3.49,
                'cost_price' => 2.10,
                'reorder_point' => 12,
                'track_batches' => true,
            ],
            [
                'slug' => 'gift-wrapping-service',
                'name' => 'Gift Wrapping Service',
                'category_id' => $snacks->id,
                'brand_id' => null,
                'sku' => 'DEMO-SVC-001',
                'sell_price' => 4.00,
                'cost_price' => 0,
                'reorder_point' => 0,
                'track_batches' => false,
                'type' => ProductType::Service,
            ],
        ];

        foreach ($catalog as $item) {
            $product = Product::query()->firstOrCreate(
                ['slug' => $item['slug']],
                [
                    'type' => $item['type'] ?? ProductType::Standard,
                    'name' => $item['name'],
                    'category_id' => $item['category_id'],
                    'brand_id' => $item['brand_id'],
                    'unit_id' => $unit->id,
                    'track_batches' => $item['track_batches'],
                    'is_active' => true,
                ],
            );

            $variant = ProductVariant::query()->firstOrCreate(
                ['sku' => $item['sku']],
                [
                    'product_id' => $product->id,
                    'name' => $item['name'],
                    'cost_price' => $item['cost_price'],
                    'sell_price' => $item['sell_price'],
                    'reorder_point' => $item['reorder_point'],
                    'preferred_supplier_id' => $supplier?->id,
                    'is_default' => true,
                ],
            );

            foreach (Branch::query()->where('is_active', true)->get() as $branch) {
                BranchProductPrice::query()->firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'sell_price' => $item['sell_price'],
                    ],
                );
            }
        }
    }
}
