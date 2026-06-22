<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\DTOs\Procurement\CreatePurchaseOrderData;
use App\DTOs\Procurement\PurchaseOrderLineData;
use App\DTOs\Procurement\ReceiveGrnData;
use App\DTOs\Procurement\ReceiveGrnLineData;
use App\Enums\LandedCostAllocationMethod;
use App\Enums\PoMatchStatus;
use App\Enums\ProductType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Events\Procurement\GoodsReceived;
use App\Events\Procurement\PurchaseOrderApproved;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Procurement\GoodsReceivingService;
use App\Services\Procurement\LandedCostService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\SupplierInvoiceService;
use App\Services\Procurement\SupplierLedgerService;
use App\Services\Procurement\SupplierPaymentService;
use App\Services\Procurement\SupplierService;
use App\Services\Procurement\ThreeWayMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private ProductVariant $variant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Proc Branch',
            'code' => 'PRB',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Main WH',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('owner');

        $this->supplier = app(SupplierService::class)->create([
            'name' => 'Acme Supplies',
            'email' => 'orders@acme.test',
            'currency_code' => 'USD',
        ], userId: $this->user->id);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Widget',
            'slug' => 'widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'WDG-001',
            'sell_price' => 100,
            'is_default' => true,
        ]);
    }

    public function test_full_procurement_cycle_updates_stock_and_supplier_ledger(): void
    {
        Event::fake([PurchaseOrderApproved::class, GoodsReceived::class]);

        $poService = app(PurchaseOrderService::class);
        $grnService = app(GoodsReceivingService::class);
        $invoiceService = app(SupplierInvoiceService::class);
        $paymentService = app(SupplierPaymentService::class);
        $ledger = app(SupplierLedgerService::class);

        $order = $poService->create(new CreatePurchaseOrderData(
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            currencyCode: 'USD',
            exchangeRate: 1,
            expectedDeliveryDate: now()->addDays(7)->toDateString(),
            notes: null,
            dropShip: false,
            saleId: null,
            userId: $this->user->id,
            lines: [
                new PurchaseOrderLineData(
                    variantId: $this->variant->id,
                    qtyOrdered: 10,
                    unitPrice: 50,
                    priceOverrideReason: null,
                    taxRate: 0,
                    description: null,
                ),
            ],
        ));

        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);

        $poService->submit($order, $this->user->id);
        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::Submitted, $order->status);

        SystemSetting::set('procurement', 'po_approval_threshold', 100000);
        $poService->approve($order, $this->user);
        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::Approved, $order->status);
        Event::assertDispatched(PurchaseOrderApproved::class);

        $grn = $grnService->receive($order, new ReceiveGrnData(
            purchaseOrderId: $order->id,
            warehouseId: $this->warehouse->id,
            userId: $this->user->id,
            notes: null,
            lines: [
                new ReceiveGrnLineData(
                    purchaseOrderItemId: $order->items->first()->id,
                    qtyReceived: 10,
                    batchNo: null,
                    expiryDate: null,
                    notes: null,
                ),
            ],
        ));

        Event::assertDispatched(GoodsReceived::class);
        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => 10,
        ]);

        $poItem = $order->items->first();
        $grnItem = $grn->items->first();

        $invoice = $invoiceService->createFromGrn(
            $grn,
            now()->toDateString(),
            now()->addDays(30)->toDateString(),
            [[
                'purchase_order_item_id' => $poItem->id,
                'grn_item_id' => $grnItem->id,
                'product_variant_id' => $this->variant->id,
                'qty_invoiced' => 10,
                'unit_price' => 50,
                'tax_rate' => 0,
                'discount_amount' => 0,
                'line_total' => 500,
                'functional_line_total' => 500,
            ]],
            $this->user->id,
        );

        $this->assertSame(PoMatchStatus::FullyMatched, $invoice->matchResult?->match_status);
        $this->assertGreaterThan(0, $ledger->getBalance($this->supplier->id, $this->branch->id));

        $invoice->update(['status' => SupplierInvoiceStatus::Approved]);
        $paymentService->recordPayment(
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            amount: 500,
            paymentMethod: 'cash',
            currencyCode: 'USD',
            exchangeRate: 1,
            paymentDate: now()->toDateString(),
            userId: $this->user->id,
            invoiceId: $invoice->id,
        );

        $this->assertSame(0.0, $ledger->getBalance($this->supplier->id, $this->branch->id));
    }

    public function test_three_way_matching_flags_price_variance_beyond_tolerance(): void
    {
        $matching = app(ThreeWayMatchingService::class);
        SystemSetting::set('procurement', 'matching_price_tolerance_percent', 2);

        $poService = app(PurchaseOrderService::class);
        $order = $poService->create(new CreatePurchaseOrderData(
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            currencyCode: 'USD',
            exchangeRate: 1,
            expectedDeliveryDate: null,
            notes: null,
            dropShip: false,
            saleId: null,
            userId: $this->user->id,
            lines: [
                new PurchaseOrderLineData($this->variant->id, 5, 100, null, 0, null),
            ],
        ));

        SystemSetting::set('procurement', 'po_approval_threshold', 999999);
        $poService->submit($order, $this->user->id);
        $poService->approve($order, $this->user);

        $grn = app(GoodsReceivingService::class)->receive($order, new ReceiveGrnData(
            purchaseOrderId: $order->id,
            warehouseId: $this->warehouse->id,
            userId: $this->user->id,
            notes: null,
            lines: [new ReceiveGrnLineData($order->items->first()->id, 5, null, null, null)],
        ));

        $invoice = app(SupplierInvoiceService::class)->createFromGrn(
            $grn,
            now()->toDateString(),
            null,
            [[
                'purchase_order_item_id' => $order->items->first()->id,
                'grn_item_id' => $grn->items->first()->id,
                'product_variant_id' => $this->variant->id,
                'qty_invoiced' => 5,
                'unit_price' => 110,
                'tax_rate' => 0,
                'discount_amount' => 0,
                'line_total' => 550,
                'functional_line_total' => 550,
            ]],
            $this->user->id,
        );

        $this->assertNotSame(PoMatchStatus::FullyMatched, $invoice->matchResult?->match_status);
    }

    public function test_landed_cost_allocates_by_quantity(): void
    {
        $poService = app(PurchaseOrderService::class);
        $order = $poService->create(new CreatePurchaseOrderData(
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            currencyCode: 'USD',
            exchangeRate: 1,
            expectedDeliveryDate: null,
            notes: null,
            dropShip: false,
            saleId: null,
            userId: $this->user->id,
            lines: [new PurchaseOrderLineData($this->variant->id, 4, 25, null, 0, null)],
        ));

        SystemSetting::set('procurement', 'po_approval_threshold', 999999);
        $poService->submit($order, $this->user->id);
        $poService->approve($order, $this->user);

        $grn = app(GoodsReceivingService::class)->receive($order, new ReceiveGrnData(
            purchaseOrderId: $order->id,
            warehouseId: $this->warehouse->id,
            userId: $this->user->id,
            notes: null,
            lines: [new ReceiveGrnLineData($order->items->first()->id, 4, null, null, null)],
        ));

        $entry = app(LandedCostService::class)->allocate(
            $grn,
            'freight',
            100,
            'USD',
            1,
            LandedCostAllocationMethod::Quantity,
            $this->user->id,
        );

        $this->assertCount(1, $entry->allocations);
        $this->assertEquals(100, (float) $entry->allocations->sum('allocated_amount'));
    }
}
