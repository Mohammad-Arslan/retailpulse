<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Enums\PosCartStatus;
use App\Enums\ProductType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private User $cashier;

    private Branch $branch;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Test Branch',
            'code' => 'TST',
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
            'name' => 'Test Product',
            'slug' => 'test-product',
            'unit_id' => $unit->id,
            'tax_rate' => 0.16,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-XYZ',
            'sell_price' => 1200,
            'is_default' => true,
        ]);

        Inventory::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('cashier');
        $this->cashier->branches()->attach($this->branch->id);

        $this->runCheckoutSettingsMigration();
    }

    public function test_split_tender_cash_and_card_completes_sale(): void
    {
        $cart = $this->createCompletingCart(quantity: 2, discountPercent: 10);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]))
            ->assertCreated();

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();
        $this->assertSame('pending_payment', $sale->status->value);
        $this->assertSame('2505.60', number_format((float) $sale->grand_total, 2, '.', ''));

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'card',
                'amount' => 60,
            ])
            ->assertOk();

        $sale->refresh();
        $this->assertSame('partially_paid', $sale->status->value);
        $this->assertSame('2445.60', number_format((float) $sale->balance_due, 2, '.', ''));

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => 2500,
                'amount' => 2445.60,
            ])
            ->assertOk();

        $sale->refresh();
        $this->assertSame('completed', $sale->status->value);
        $this->assertSame('0.00', number_format((float) $sale->balance_due, 2, '.', ''));
        $this->assertCount(2, $sale->payments);
        $this->assertNotNull($sale->invoice);
    }

    public function test_cash_change_is_stored_in_payment_meta(): void
    {
        SystemSetting::set('split_tender', 'enabled', false, 'boolean');

        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]));

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();
        $balance = (float) $sale->balance_due;

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => 120,
                'amount' => $balance,
            ])
            ->assertOk();

        $payment = $sale->fresh()->payments->first();
        $this->assertSame(number_format($balance, 2, '.', ''), number_format((float) $payment->amount, 2, '.', ''));
        $this->assertSame(120.0, (float) $payment->meta['tendered_amount']);
    }

    public function test_completed_sale_rejects_additional_payments(): void
    {
        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]));

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => 2000,
            ]);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => 100,
            ])
            ->assertStatus(422);
    }

    public function test_abandon_checkout_restores_cart_to_active(): void
    {
        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.abandon', ['cartId' => $cart->id]))
            ->assertOk();

        $cart->refresh();
        $this->assertSame(PosCartStatus::Active, $cart->status);
        $this->assertDatabaseMissing('sales', ['cart_id' => $cart->id]);
    }

    public function test_tax_calculation_for_exclusive_sixteen_percent(): void
    {
        $cart = $this->createCompletingCart(quantity: 2, discountPercent: 10);

        $response = $this->actingAs($this->cashier)
            ->getJson(route('api.v1.checkout.show', ['cartId' => $cart->id]))
            ->assertOk();

        $response->assertJsonPath('tax_total', '345.60');
        $response->assertJsonPath('grand_total', '2505.60');
    }

    private function createCompletingCart(int $quantity, ?float $discountPercent = null): PosCart
    {
        $lineTotal = PosCartItem::computeLineTotal(
            unitPrice: 1200,
            quantity: $quantity,
            discountType: $discountPercent !== null ? 'percent' : null,
            discountValue: $discountPercent,
        );

        $cart = PosCart::query()->create([
            'cashier_id' => $this->cashier->id,
            'branch_id' => $this->branch->id,
            'status' => PosCartStatus::Completing,
            'slot' => 1,
        ]);

        PosCartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'sku' => $this->variant->sku,
            'name' => 'Test Product',
            'unit_price' => 1200,
            'quantity' => $quantity,
            'discount_type' => $discountPercent !== null ? 'percent' : null,
            'discount_value' => $discountPercent,
            'line_total' => $lineTotal,
        ]);

        return $cart;
    }

    private function runCheckoutSettingsMigration(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_05_26_100004_seed_checkout_settings.php']);
    }
}
