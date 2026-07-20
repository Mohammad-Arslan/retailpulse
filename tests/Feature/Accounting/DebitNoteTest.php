<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\DTOs\Procurement\CreatePurchaseOrderData;
use App\DTOs\Procurement\PurchaseOrderLineData;
use App\DTOs\Procurement\ReceiveGrnData;
use App\DTOs\Procurement\ReceiveGrnLineData;
use App\Enums\AccountingEventStatus;
use App\Enums\ProductType;
use App\Enums\PurchaseReturnStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Events\Procurement\DebitNoteIssued;
use App\Models\Branch;
use App\Models\BranchAccountingProfile;
use App\Models\DebitNote;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Procurement\GoodsReceivingService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\Assert;
use Tests\Concerns\SeedsAccounting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class DebitNoteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;
    use SeedsRbac;

    private Branch $branch;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private ProductVariant $variant;

    private User $ownerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedAccounting();

        $this->branch = Branch::query()->create([
            'name' => 'Debit Note Branch',
            'code' => 'DNB',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        BranchAccountingProfile::query()->updateOrCreate(
            ['branch_id' => $this->branch->id],
            ['accounting_enabled_modules' => ['core', 'ar_ap', 'debit_notes']],
        );

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Main WH',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'code' => 'SUP-DN',
            'name' => 'Debit Note Supplier',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Widget',
            'slug' => 'widget-dn',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'WDG-DN',
            'sell_price' => 100,
            'is_default' => true,
        ]);

        $this->ownerUser = User::factory()->create(['is_active' => true]);
        $this->ownerUser->assignRole('owner');
    }

    private function actingAsOwner()
    {
        return $this->actingAs($this->ownerUser)->withSession(['branch_id' => $this->branch->id]);
    }

    public function test_standalone_creation_against_supplier_invoice_fires_event_and_posts(): void
    {
        Event::fake([DebitNoteIssued::class]);

        $invoice = SupplierInvoice::query()->create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'reference_no' => 'SINV-DN-001',
            'status' => SupplierInvoiceStatus::Approved,
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 1,
            'total' => 500,
            'functional_total' => 500,
            'created_by' => $this->ownerUser->id,
        ]);

        $response = $this->actingAsOwner()->post(route('admin.accounting.debit-notes.store'), [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'supplier_invoice_id' => $invoice->id,
            'date' => now()->toDateString(),
            'amount' => 100,
            'currency_code' => 'USD',
            'reason' => 'Price correction on invoice',
        ]);

        $response->assertRedirect(route('admin.accounting.debit-notes.index'));

        $debitNote = DebitNote::query()->where('supplier_invoice_id', $invoice->id)->firstOrFail();
        $this->assertNull($debitNote->purchase_return_id);
        $this->assertSame(100.0, (float) $debitNote->amount);
        $this->assertSame($this->supplier->id, $debitNote->supplier_id);

        Event::assertDispatched(DebitNoteIssued::class, fn ($event) => $event->debitNote->is($debitNote));

        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $this->supplier->id,
            'reference_type' => DebitNote::class,
            'reference_id' => $debitNote->id,
        ]);
    }

    public function test_standalone_creation_rejects_amount_exceeding_invoice_remaining_balance(): void
    {
        $invoice = SupplierInvoice::query()->create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'reference_no' => 'SINV-DN-002',
            'status' => SupplierInvoiceStatus::Approved,
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 1,
            'total' => 100,
            'functional_total' => 100,
            'created_by' => $this->ownerUser->id,
        ]);

        $this->actingAsOwner()->post(route('admin.accounting.debit-notes.store'), [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'supplier_invoice_id' => $invoice->id,
            'date' => now()->toDateString(),
            'amount' => 200,
            'currency_code' => 'USD',
            'reason' => 'Overcorrection attempt',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('debit_notes', ['supplier_invoice_id' => $invoice->id]);
    }

    public function test_purchase_return_debit_note_flow_still_works_after_refactor(): void
    {
        Event::fake([DebitNoteIssued::class]);

        $grn = $this->receiveGrn(qty: 10, unitPrice: 50);

        $return = app(PurchaseReturnService::class)->create(
            $grn,
            'Damaged on arrival',
            [[
                'grn_item_id' => $grn->items->first()->id,
                'qty_returned' => 4,
                'unit_cost' => 50,
            ]],
            $this->ownerUser->id,
        );

        app(PurchaseReturnService::class)->approve($return, $this->ownerUser->id);
        app(PurchaseReturnService::class)->dispatchGoods($return->fresh(), $this->ownerUser->id, $this->warehouse->id);

        $response = $this->actingAsOwner()->post(route('admin.purchase-returns.debit-note', $return->id));
        $response->assertRedirect();

        $debitNote = DebitNote::query()->where('purchase_return_id', $return->id)->firstOrFail();
        $this->assertSame(200.0, (float) $debitNote->amount);
        $this->assertNull($debitNote->supplier_invoice_id);

        Event::assertDispatched(DebitNoteIssued::class, fn ($event) => $event->debitNote->is($debitNote));

        $return->refresh();
        $this->assertSame(PurchaseReturnStatus::DebitNoteIssued, $return->status);

        $this->assertDatabaseHas('accounting_events', [
            'event_type' => 'debit_note.issued',
            'source_type' => DebitNote::class,
            'source_id' => $debitNote->id,
            'processing_status' => AccountingEventStatus::Completed->value,
        ]);
    }

    public function test_index_lists_debit_notes_via_presenter(): void
    {
        $invoice = SupplierInvoice::query()->create([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'reference_no' => 'SINV-DN-003',
            'status' => SupplierInvoiceStatus::Approved,
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 1,
            'total' => 500,
            'functional_total' => 500,
            'created_by' => $this->ownerUser->id,
        ]);

        $this->actingAsOwner()->post(route('admin.accounting.debit-notes.store'), [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'supplier_invoice_id' => $invoice->id,
            'date' => now()->toDateString(),
            'amount' => 100,
            'currency_code' => 'USD',
            'reason' => 'Listed note',
        ]);

        $this->actingAsOwner()
            ->get(route('admin.accounting.debit-notes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/DebitNotes/Index')
                ->has('debitNotes.data', 1)
                ->where('debitNotes.data.0.supplier_name', $this->supplier->name)
            );
    }

    public function test_user_without_procurement_permissions_cannot_view_or_create(): void
    {
        $plainUser = User::factory()->create(['is_active' => true]);

        $this->actingAs($plainUser)->withSession(['branch_id' => $this->branch->id])
            ->get(route('admin.accounting.debit-notes.index'))
            ->assertForbidden();

        $this->actingAs($plainUser)->withSession(['branch_id' => $this->branch->id])
            ->get(route('admin.accounting.debit-notes.create'))
            ->assertForbidden();

        $this->actingAs($plainUser)->withSession(['branch_id' => $this->branch->id])
            ->post(route('admin.accounting.debit-notes.store'), [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'date' => now()->toDateString(),
                'amount' => 50,
                'reason' => 'Should not be allowed',
            ])
            ->assertForbidden();
    }

    private function receiveGrn(int $qty, float $unitPrice)
    {
        SystemSetting::set('procurement', 'po_approval_threshold', 100000);

        $poService = app(PurchaseOrderService::class);
        $order = $poService->create(new CreatePurchaseOrderData(
            branchId: $this->branch->id,
            supplierId: $this->supplier->id,
            currencyCode: 'USD',
            exchangeRate: 1,
            expectedDeliveryDate: now()->addDays(7)->toDateString(),
            notes: null,
            dropShip: false,
            saleId: null,
            userId: $this->ownerUser->id,
            lines: [
                new PurchaseOrderLineData(
                    variantId: $this->variant->id,
                    qtyOrdered: $qty,
                    unitPrice: $unitPrice,
                    priceOverrideReason: null,
                    taxRate: 0,
                    description: null,
                ),
            ],
        ));

        $poService->submit($order, $this->ownerUser->id);
        $poService->approve($order, $this->ownerUser);

        return app(GoodsReceivingService::class)->receive($order->fresh(), new ReceiveGrnData(
            purchaseOrderId: $order->id,
            warehouseId: $this->warehouse->id,
            userId: $this->ownerUser->id,
            notes: null,
            lines: [
                new ReceiveGrnLineData(
                    purchaseOrderItemId: $order->items->first()->id,
                    qtyReceived: $qty,
                    batchNo: null,
                    expiryDate: null,
                    notes: null,
                ),
            ],
        ));
    }
}
