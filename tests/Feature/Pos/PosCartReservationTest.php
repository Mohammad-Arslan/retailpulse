<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\DTOs\Pos\AddCartItemData;
use App\Enums\PosCartStatus;
use App\Enums\ProductType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PosCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class PosCartReservationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'POS Branch',
            'code' => 'POS',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'name' => 'Piece',
            'abbreviation' => 'pc',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Reserved Widget',
            'slug' => 'reserved-widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'RSV-001',
            'sell_price' => 500,
            'is_default' => true,
        ]);

        Inventory::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'quantity_in_quarantine' => 0,
        ]);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');
        $this->cashier->branches()->attach($this->branch->id);
    }

    public function test_adding_cart_item_reserves_stock(): void
    {
        $service = app(PosCartService::class);
        $cart = $service->createCart($this->cashier->id, $this->branch->id);

        $service->addItem($cart, new AddCartItemData(
            productVariantId: $this->variant->id,
            quantity: 3,
        ));

        $inventory = Inventory::query()->first();
        $this->assertSame(10, $inventory?->quantity_on_hand);
        $this->assertSame(3, $inventory?->quantity_reserved);

        $this->assertDatabaseHas('stock_reservations', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
            'reference_type' => 'pos_cart_item',
            'released_at' => null,
        ]);
    }

    public function test_second_cart_cannot_oversell_reserved_stock(): void
    {
        $service = app(PosCartService::class);

        $cartA = $service->createCart($this->cashier->id, $this->branch->id);
        $service->addItem($cartA, new AddCartItemData(
            productVariantId: $this->variant->id,
            quantity: 7,
        ));

        $cartB = $service->createCart($this->cashier->id, $this->branch->id);

        $this->expectException(ValidationException::class);
        $service->addItem($cartB, new AddCartItemData(
            productVariantId: $this->variant->id,
            quantity: 5,
        ));
    }

    public function test_void_cart_releases_reservations(): void
    {
        $service = app(PosCartService::class);
        $cart = $service->createCart($this->cashier->id, $this->branch->id);

        $service->addItem($cart, new AddCartItemData(
            productVariantId: $this->variant->id,
            quantity: 4,
        ));

        $service->voidCart($cart->fresh());

        $inventory = Inventory::query()->first();
        $this->assertSame(0, $inventory?->quantity_reserved);
        $this->assertSame(0, StockReservation::query()->whereNull('released_at')->count());
    }

    public function test_checkout_deduct_clears_reserved_without_double_release(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_05_26_100004_seed_checkout_settings.php']);

        $service = app(PosCartService::class);
        $cart = $service->createCart($this->cashier->id, $this->branch->id);

        $service->addItem($cart, new AddCartItemData(
            productVariantId: $this->variant->id,
            quantity: 2,
        ));

        $cart = $cart->fresh();
        $service->checkoutCart($cart);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]))
            ->assertCreated();

        $inventory = Inventory::query()->first();
        $this->assertSame(8, $inventory?->quantity_on_hand);
        $this->assertSame(0, $inventory?->quantity_reserved);
        $this->assertSame(PosCartStatus::Completed, $cart->fresh()->status);
    }
}
