<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class VariableProductPricingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        Unit::query()->create([
            'name' => 'Each',
            'abbreviation' => 'EA',
            'is_active' => true,
        ]);

        $this->category = Category::query()->create([
            'name' => 'Apparel',
            'slug' => 'apparel',
            'is_active' => true,
        ]);
    }

    public function test_variable_product_create_persists_per_variant_sell_prices(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'type' => ProductType::Variable->value,
                'name' => 'T-Shirt',
                'description' => '',
                'category_id' => $this->category->id,
                'brand_id' => null,
                'unit_id' => null,
                'track_batches' => false,
                'is_active' => true,
                'default_cost_price' => '5',
                'default_sell_price' => '20',
                'default_reorder_point' => '',
                'default_preferred_supplier_id' => null,
                'default_alternate_supplier_ids' => [],
                'variant_attributes' => [
                    ['name' => 'Size', 'options' => ['S', 'M', 'L']],
                ],
                'variants' => [
                    [
                        'name' => 'S',
                        'attributes' => ['Size' => 'S'],
                        'cost_price' => '4',
                        'sell_price' => '15',
                    ],
                    [
                        'name' => 'M',
                        'attributes' => ['Size' => 'M'],
                        'cost_price' => '5',
                        'sell_price' => '20',
                    ],
                    [
                        'name' => 'L',
                        'attributes' => ['Size' => 'L'],
                        'cost_price' => '6',
                        'sell_price' => '25',
                    ],
                ],
                'bundle_items' => [],
                'branch_prices' => [],
            ])
            ->assertRedirect();

        $product = Product::query()->where('name', 'T-Shirt')->firstOrFail();

        $this->assertSame(ProductType::Variable, $product->type);
        $this->assertCount(3, $product->variants);

        $prices = $product->variants()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant) => [
                $variant->attributes['Size'] => (string) $variant->sell_price,
            ])
            ->all();

        $this->assertSame([
            'S' => '15.0000',
            'M' => '20.0000',
            'L' => '25.0000',
        ], $prices);
    }
}
