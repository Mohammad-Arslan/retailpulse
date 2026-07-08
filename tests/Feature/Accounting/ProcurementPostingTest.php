<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\DTOs\Procurement\CreatePurchaseOrderData;
use App\DTOs\Procurement\PurchaseOrderLineData;
use App\DTOs\Procurement\ReceiveGrnData;
use App\DTOs\Procurement\ReceiveGrnLineData;
use App\Enums\LandedCostAllocationMethod;
use App\Enums\ProductType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceivingNote;
use App\Models\GrnItem;
use App\Models\InventoryCostLayer;
use App\Models\JournalEntry;
use App\Models\LandedCostAllocation;
use App\Models\LandedCostEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\CostService;
use App\Services\Accounting\ProcurementPostingService;
use App\Services\Procurement\GoodsReceivingService;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccounting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class ProcurementPostingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;
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
        $this->seedAccounting();

        $this->branch = Branch::query()->create([
            'name' => 'Proc GL Branch',
            'code' => 'PGL',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'WH',
            'code' => 'WH1',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('owner');

        $this->supplier = Supplier::query()->create([
            'code' => 'SUP-GL',
            'name' => 'Supplier GL',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Stock Item',
            'slug' => 'stock-item-gl',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'STK-GL',
            'sell_price' => 20,
            'is_default' => true,
        ]);
    }

    public function test_grn_posts_dr_inventory_cr_accounts_payable(): void
    {
        $grn = $this->receiveGrn(qty: 100, unitPrice: 10);

        $journal = app(ProcurementPostingService::class)->postGrnToGL($grn);

        $this->assertNotNull($journal);

        $inventory = ChartOfAccount::query()->where('code', '1400')->firstOrFail();
        $ap = ChartOfAccount::query()->where('code', '2100')->firstOrFail();

        $transactions = JournalEntry::query()->with('transactions')->findOrFail($journal->id)->transactions;

        $this->assertSame(1000.0, (float) $transactions->where('account_id', $inventory->id)->sum('debit'));
        $this->assertSame(1000.0, (float) $transactions->where('account_id', $ap->id)->sum('credit'));
    }

    public function test_landed_cost_allocation_updates_inventory_cost_layer(): void
    {
        $grn = $this->receiveGrn(qty: 10, unitPrice: 100);
        $grnItem = $grn->items->first();

        $layer = InventoryCostLayer::query()
            ->where('source_reference_type', GrnItem::class)
            ->where('source_reference_id', $grnItem->id)
            ->first();

        $this->assertNotNull($layer);
        $this->assertSame(100.0, (float) $layer->unit_cost);

        $entry = LandedCostEntry::query()->create([
            'grn_id' => $grn->id,
            'charge_type' => 'freight',
            'description' => 'Freight',
            'amount' => 50,
            'functional_amount' => 50,
            'currency_code' => 'USD',
            'exchange_rate' => 1,
            'allocation_method' => LandedCostAllocationMethod::Value,
            'created_by' => $this->user->id,
        ]);

        LandedCostAllocation::query()->create([
            'landed_cost_entry_id' => $entry->id,
            'grn_item_id' => $grnItem->id,
            'allocated_amount' => 50,
            'functional_amount' => 50,
        ]);

        app(CostService::class)->applyLandedCost($entry->fresh(['allocations.grnItem']));

        $layer->refresh();
        $this->assertSame(105.0, (float) $layer->unit_cost);
        $this->assertSame(50.0, (float) $layer->landed_cost_amount);
    }

    private function receiveGrn(int $qty, float $unitPrice): GoodsReceivingNote
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
            userId: $this->user->id,
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

        $poService->submit($order, $this->user->id);
        $poService->approve($order, $this->user);

        return app(GoodsReceivingService::class)->receive($order->fresh(), new ReceiveGrnData(
            purchaseOrderId: $order->id,
            warehouseId: $this->warehouse->id,
            userId: $this->user->id,
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
