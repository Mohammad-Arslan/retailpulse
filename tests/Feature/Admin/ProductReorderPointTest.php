<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class ProductReorderPointTest extends TestCase
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

        $this->category = Category::query()->create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);
    }

    public function test_setting_reorder_point_via_product_update_persists_on_the_variant(): void
    {
        $product = Product::query()->create([
            'name' => 'Reorder Widget',
            'slug' => 'reorder-widget',
            'type' => ProductType::Standard,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Reorder Widget',
            'sku' => 'REORDER-001',
            'barcode' => '3234567890123',
            'cost_price' => 5,
            'sell_price' => 10,
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $this->assertNull($variant->reorder_point);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Reorder Widget',
                'description' => '',
                'category_id' => $this->category->id,
                'brand_id' => null,
                'unit_id' => null,
                'track_batches' => false,
                'is_active' => true,
                'regenerate_variants' => false,
                'default_cost_price' => '5',
                'default_sell_price' => '10',
                'default_reorder_point' => '',
                'variants' => [[
                    'id' => $variant->id,
                    'name' => 'Reorder Widget',
                    'sku' => 'REORDER-001',
                    'barcode' => '3234567890123',
                    'cost_price' => '5',
                    'sell_price' => '10',
                    'reorder_point' => '15',
                ]],
                'bundle_items' => [],
                'branch_prices' => [],
            ])
            ->assertRedirect(route('admin.products.edit', $product));

        $variant->refresh();
        $this->assertSame(15, $variant->reorder_point);

        // Round-trips on a subsequent edit-page load.
        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Edit')
                ->where('product.variants.0.reorder_point', '15'));
    }

    public function test_reorder_point_must_be_a_non_negative_integer(): void
    {
        $product = Product::query()->create([
            'name' => 'Invalid Reorder Widget',
            'slug' => 'invalid-reorder-widget',
            'type' => ProductType::Standard,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Invalid Reorder Widget',
            'sku' => 'REORDER-002',
            'barcode' => '4234567890123',
            'cost_price' => 5,
            'sell_price' => 10,
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Invalid Reorder Widget',
                'description' => '',
                'category_id' => $this->category->id,
                'brand_id' => null,
                'unit_id' => null,
                'track_batches' => false,
                'is_active' => true,
                'regenerate_variants' => false,
                'default_cost_price' => '5',
                'default_sell_price' => '10',
                'default_reorder_point' => '',
                'variants' => [[
                    'id' => $variant->id,
                    'name' => 'Invalid Reorder Widget',
                    'sku' => 'REORDER-002',
                    'barcode' => '4234567890123',
                    'cost_price' => '5',
                    'sell_price' => '10',
                    'reorder_point' => '-5',
                ]],
                'bundle_items' => [],
                'branch_prices' => [],
            ])
            ->assertSessionHasErrors('variants.0.reorder_point');

        $this->assertNull($variant->fresh()->reorder_point);
    }
}
