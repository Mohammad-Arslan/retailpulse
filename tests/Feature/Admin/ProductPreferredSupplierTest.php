<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\ImportExport\Handlers\ProductImportHandler;
use App\Services\ImportExport\ImportContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class ProductPreferredSupplierTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private User $admin;

    private Category $category;

    private Supplier $supplier;

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
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'code' => 'SUP-ACME',
            'name' => 'Acme Wholesale',
            'slug' => 'acme-wholesale',
            'is_active' => true,
        ]);
    }

    public function test_product_show_displays_preferred_supplier_name(): void
    {
        $product = Product::query()->create([
            'name' => 'Widget',
            'slug' => 'widget',
            'type' => ProductType::Standard,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-001',
            'barcode' => '1234567890123',
            'cost_price' => 5,
            'sell_price' => 10,
            'preferred_supplier_id' => $this->supplier->id,
            'alternate_supplier_ids' => null,
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Show')
                ->where('product.variants.0.preferred_supplier.name', 'Acme Wholesale')
                ->where('product.variants.0.preferred_supplier.code', 'SUP-ACME'));
    }

    public function test_product_update_persists_preferred_and_alternate_suppliers(): void
    {
        $alternate = Supplier::query()->create([
            'code' => 'SUP-GLOBAL',
            'name' => 'Global Distributors',
            'slug' => 'global-distributors',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'name' => 'Gadget',
            'slug' => 'gadget',
            'type' => ProductType::Standard,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Gadget',
            'sku' => 'GADGET-001',
            'barcode' => '2234567890123',
            'cost_price' => 8,
            'sell_price' => 15,
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Gadget',
                'description' => '',
                'category_id' => $this->category->id,
                'brand_id' => null,
                'unit_id' => null,
                'track_batches' => false,
                'is_active' => true,
                'regenerate_variants' => false,
                'default_cost_price' => '8',
                'default_sell_price' => '15',
                'default_reorder_point' => '',
                'default_preferred_supplier_id' => $this->supplier->id,
                'default_alternate_supplier_ids' => [$alternate->id],
                'variants' => [[
                    'id' => $variant->id,
                    'name' => 'Gadget',
                    'sku' => 'GADGET-001',
                    'barcode' => '2234567890123',
                    'cost_price' => '8',
                    'sell_price' => '15',
                    'reorder_point' => '',
                ]],
                'bundle_items' => [],
                'branch_prices' => [],
            ])
            ->assertRedirect(route('admin.products.edit', $product));

        $variant->refresh();

        $this->assertSame($this->supplier->id, $variant->preferred_supplier_id);
        $this->assertSame([$alternate->id], $variant->alternate_supplier_ids);
    }

    public function test_product_import_reports_invalid_preferred_supplier_code_without_blocking_other_rows(): void
    {
        $handler = new ProductImportHandler;
        $context = new ImportContext(
            jobId: 1,
            tenantId: null,
            userId: $this->admin->id,
            mode: 'create',
            isDryRun: true,
            filePath: 'products.csv',
            disk: 'local',
            options: ['match_field' => 'sku'],
        );

        $validRow = [
            'name' => 'Valid Product',
            'sku' => 'VALID-001',
            'category_code' => 'general',
            'sell_price' => 12,
            'preferred_supplier_code' => 'SUP-ACME',
        ];

        $invalidRow = [
            'name' => 'Invalid Supplier Product',
            'sku' => 'INVALID-001',
            'category_code' => 'general',
            'sell_price' => 12,
            'preferred_supplier_code' => 'SUP-MISSING',
        ];

        $this->assertSame([], $handler->validateRow($validRow, $context));
        $this->assertArrayHasKey(
            'preferred_supplier_code',
            $handler->validateRow($invalidRow, $context),
        );
    }
}
