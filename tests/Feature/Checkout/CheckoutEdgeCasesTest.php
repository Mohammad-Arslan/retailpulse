<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Enums\FbrInvoiceStatus;
use App\Enums\PosCartStatus;
use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Enums\TaxMode;
use App\Jobs\SubmitFbrInvoiceJob;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\PosCart;
use App\Models\PosCartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleInvoice;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class CheckoutEdgeCasesTest extends TestCase
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
            'sell_price' => 1000,
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

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_05_26_100004_seed_checkout_settings.php']);
    }

    // -------------------------------------------------------------------------
    // Layaway
    // -------------------------------------------------------------------------

    public function test_layaway_partial_payment_creates_partially_paid_sale(): void
    {
        SystemSetting::set('layaway', 'enabled', true, 'boolean');
        SystemSetting::set('split_tender', 'enabled', false, 'boolean');

        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]))
            ->assertCreated();

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => 300,
                'amount' => 300,
            ])
            ->assertOk();

        $sale->refresh();
        $this->assertSame('partially_paid', $sale->status->value);
        $this->assertGreaterThan(0, (float) $sale->balance_due);
    }

    public function test_layaway_second_payment_completes_sale(): void
    {
        SystemSetting::set('layaway', 'enabled', true, 'boolean');
        SystemSetting::set('split_tender', 'enabled', false, 'boolean');

        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]));

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();
        $total = (float) $sale->grand_total;
        $deposit = 300.0;
        $remaining = round($total - $deposit, 2);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => $deposit,
                'amount' => $deposit,
            ]);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => $remaining,
                'amount' => $remaining,
            ])
            ->assertOk();

        $sale->refresh();
        $this->assertSame('completed', $sale->status->value);
        $this->assertSame('0.00', number_format((float) $sale->balance_due, 2, '.', ''));
        $this->assertNotNull($sale->invoice);
    }

    // -------------------------------------------------------------------------
    // Void sale
    // -------------------------------------------------------------------------

    public function test_void_sale_without_payments_restores_cart_to_voided(): void
    {
        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]));

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.void', ['id' => $sale->id]))
            ->assertOk();

        $cart->refresh();
        $this->assertSame(PosCartStatus::Voided, $cart->status);
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
    }

    public function test_void_sale_with_completed_payment_is_rejected(): void
    {
        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]));

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();
        $balance = (float) $sale->balance_due;

        // Pay in full — sale becomes completed
        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => $balance,
                'amount' => $balance,
            ]);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.void', ['id' => $sale->id]))
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // FBR queue mode
    // -------------------------------------------------------------------------

    public function test_fbr_queue_mode_completes_sale_and_queues_job(): void
    {
        Queue::fake();

        SystemSetting::set('fbr', 'enabled', true, 'boolean');
        SystemSetting::set('fbr', 'failure_mode', 'queue');

        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]));

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();
        $balance = (float) $sale->balance_due;

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'cash',
                'tendered_amount' => $balance,
                'amount' => $balance,
            ])
            ->assertOk();

        $sale->refresh();
        $this->assertSame('completed', $sale->status->value);
        $this->assertNotNull($sale->invoice);
        $this->assertSame(FbrInvoiceStatus::Pending, $sale->invoice->fbr_status);
        $this->assertDatabaseHas('fbr_invoice_queue', [
            'sale_invoice_id' => $sale->invoice->id,
            'status' => 'pending',
        ]);
        Queue::assertPushed(SubmitFbrInvoiceJob::class);
    }

    // -------------------------------------------------------------------------
    // Credit sale requires customer
    // -------------------------------------------------------------------------

    public function test_credit_payment_without_customer_returns_422(): void
    {
        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.checkout.confirm', ['cartId' => $cart->id]));

        $sale = Sale::query()->where('cart_id', $cart->id)->firstOrFail();

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.payments.store', ['id' => $sale->id]), [
                'method' => 'credit',
                'amount' => (float) $sale->balance_due,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');
    }

    // -------------------------------------------------------------------------
    // Historical import
    // -------------------------------------------------------------------------

    public function test_historical_import_creates_completed_sales_without_inventory_movement(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('owner');
        $owner->branches()->attach($this->branch->id);

        $initialMovements = InventoryMovement::query()->count();

        $payload = [
            'sales' => [
                [
                    'branch_id' => $this->branch->id,
                    'sale_date' => now()->subDays(5)->toDateTimeString(),
                    'subtotal' => 1000.00,
                    'total_discount' => 0,
                    'tax_total' => 160.00,
                    'grand_total' => 1160.00,
                    'currency' => 'PKR',
                    'invoice_number' => 'HIST-TEST-001',
                    'items' => [
                        [
                            'product_id' => $this->variant->product_id,
                            'variant_id' => $this->variant->id,
                            'sku' => 'SKU-XYZ',
                            'name' => 'Test Product',
                            'unit_price' => 1000.00,
                            'quantity' => 1,
                            'line_total' => 1000.00,
                            'tax_rate' => 0.16,
                            'tax_amount' => 160.00,
                            'line_total_inc_tax' => 1160.00,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($owner)
            ->postJson(route('api.v1.sales.import-historical'), $payload)
            ->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 0);

        $sale = Sale::query()->where('is_historical', true)->firstOrFail();
        $this->assertSame('completed', $sale->status->value);
        $this->assertSame('0.00', number_format((float) $sale->balance_due, 2, '.', ''));
        $this->assertDatabaseHas('sale_invoices', [
            'sale_id' => $sale->id,
            'number' => 'HIST-TEST-001',
        ]);

        // No inventory movements should have been posted
        $this->assertSame($initialMovements, InventoryMovement::query()->count());
    }

    public function test_historical_import_rejects_future_sale_date(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('owner');
        $owner->branches()->attach($this->branch->id);

        $payload = [
            'sales' => [
                [
                    'branch_id' => $this->branch->id,
                    'sale_date' => now()->addDay()->toDateTimeString(),
                    'grand_total' => 1000.00,
                    'items' => [
                        ['product_id' => $this->variant->product_id, 'quantity' => 1],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($owner)
            ->postJson(route('api.v1.sales.import-historical'), $payload);

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(0, $data['imported']);
        $this->assertSame(1, $data['skipped']);
        $this->assertStringContainsString('past', $data['errors'][0]['reason']);
    }

    public function test_historical_import_rejects_duplicate_invoice_number(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('owner');
        $owner->branches()->attach($this->branch->id);

        $sale = Sale::query()->create([
            'branch_id' => $this->branch->id,
            'cashier_id' => $owner->id,
            'status' => SaleStatus::Completed,
            'subtotal' => 100,
            'total_discount' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'balance_due' => 0,
            'currency' => 'PKR',
            'tax_mode' => TaxMode::Exclusive,
            'is_historical' => true,
            'completed_at' => now()->subDay(),
        ]);

        SaleInvoice::query()->create([
            'sale_id' => $sale->id,
            'number' => 'DUP-001',
            'template' => 'a4',
            'public_token' => (string) Str::uuid(),
            'fbr_status' => FbrInvoiceStatus::NotApplicable,
        ]);

        $payload = [
            'sales' => [
                [
                    'branch_id' => $this->branch->id,
                    'sale_date' => now()->subDays(2)->toDateTimeString(),
                    'grand_total' => 100.00,
                    'invoice_number' => 'DUP-001',
                    'items' => [
                        ['product_id' => $this->variant->product_id, 'quantity' => 1],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($owner)
            ->postJson(route('api.v1.sales.import-historical'), $payload)
            ->assertOk();

        $this->assertSame(0, $response->json('imported'));
        $this->assertSame(1, $response->json('skipped'));
    }

    public function test_historical_import_requires_permission(): void
    {
        $cart = $this->createCompletingCart(quantity: 1);

        $this->actingAs($this->cashier)
            ->postJson(route('api.v1.sales.import-historical'), ['sales' => []])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Sales export
    // -------------------------------------------------------------------------

    public function test_sales_export_returns_csv(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('owner');
        $owner->branches()->attach($this->branch->id);

        $this->actingAs($owner)
            ->getJson(route('api.v1.sales.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_sales_export_requires_permission(): void
    {
        // cashier role doesn't have sales.export
        $this->actingAs($this->cashier)
            ->getJson(route('api.v1.sales.export'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Customer search
    // -------------------------------------------------------------------------

    public function test_customer_search_returns_matching_customers(): void
    {
        Customer::query()->create(['name' => 'Ali Hassan', 'phone' => '03001234567', 'is_active' => true]);
        Customer::query()->create(['name' => 'Sara Khan', 'phone' => '03009876543', 'is_active' => true]);

        $response = $this->actingAs($this->cashier)
            ->getJson(route('api.v1.customers.search', ['q' => 'Ali']))
            ->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertSame('Ali Hassan', $response->json('0.name'));
    }

    public function test_customer_search_returns_empty_for_no_match(): void
    {
        Customer::query()->create(['name' => 'Ali Hassan', 'phone' => '03001234567', 'is_active' => true]);

        $this->actingAs($this->cashier)
            ->getJson(route('api.v1.customers.search', ['q' => 'XYZ']))
            ->assertOk()
            ->assertExactJson([]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCompletingCart(int $quantity): PosCart
    {
        $lineTotal = PosCartItem::computeLineTotal(
            unitPrice: 1000,
            quantity: $quantity,
            discountType: null,
            discountValue: null,
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
            'unit_price' => 1000,
            'quantity' => $quantity,
            'discount_type' => null,
            'discount_value' => null,
            'line_total' => $lineTotal,
        ]);

        return $cart;
    }
}
