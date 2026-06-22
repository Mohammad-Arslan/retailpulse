<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GrnStatus;
use App\Enums\PurchaseReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceivingNote;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Procurement\ProcurementConfigService;
use App\Support\BranchContext;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GoodsReceivingNoteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $branchId = app(BranchContext::class)->branchId;
        $filters = ListPagination::filters($request, ['search', 'status', 'supplier_id']);

        $paginator = GoodsReceivingNote::query()
            ->with(['supplier', 'purchaseOrder', 'warehouse'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where('reference_no', 'like', $term);
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn ($q, $supplierId) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('received_at')
            ->paginate(ListPagination::resolve($filters['per_page'] ?? 15))
            ->withQueryString();

        return Inertia::render('Admin/GoodsReceivingNotes/Index', [
            'grns' => $paginator->through(fn (GoodsReceivingNote $g) => [
                'id' => $g->id,
                'reference_no' => $g->reference_no,
                'status' => $g->status->value,
                'supplier' => $g->supplier ? ['id' => $g->supplier->id, 'name' => $g->supplier->name] : null,
                'purchase_order' => $g->purchaseOrder ? ['id' => $g->purchaseOrder->id, 'reference_no' => $g->purchaseOrder->reference_no] : null,
                'warehouse' => $g->warehouse?->name,
                'received_at' => $g->received_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'statuses' => GrnStatus::values(),
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function show(GoodsReceivingNote $goodsReceivingNote): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $grn = $goodsReceivingNote->load([
            'supplier',
            'purchaseOrder.sale',
            'purchaseOrder.items',
            'warehouse',
            'items.variant',
            'items.purchaseOrderItem',
            'supplierInvoices.matchResult',
            'purchaseReturns.items',
            'purchaseReturns.debitNote',
            'landedCostEntries.allocations',
        ]);

        $returnedByGrnItem = [];
        foreach ($grn->purchaseReturns as $return) {
            if ($return->status === PurchaseReturnStatus::Closed) {
                continue;
            }
            foreach ($return->items as $returnItem) {
                $returnedByGrnItem[$returnItem->grn_item_id] = ($returnedByGrnItem[$returnItem->grn_item_id] ?? 0)
                    + (float) $returnItem->qty_returned;
            }
        }

        $poFullyReceived = $grn->purchaseOrder?->items->every(
            fn ($poItem) => (float) $poItem->qty_received >= (float) $poItem->qty_ordered,
        ) ?? true;

        $branchId = $grn->branch_id;
        $config = app(ProcurementConfigService::class)->resolve($branchId);

        return Inertia::render('Admin/GoodsReceivingNotes/Show', [
            'grn' => [
                'id' => $grn->id,
                'reference_no' => $grn->reference_no,
                'status' => $grn->status->value,
                'received_at' => $grn->received_at?->toIso8601String(),
                'branch_id' => $grn->branch_id,
                'supplier' => $grn->supplier ? ['id' => $grn->supplier->id, 'name' => $grn->supplier->name, 'currency_code' => $grn->supplier->currency_code] : null,
                'purchase_order' => $grn->purchaseOrder ? [
                    'id' => $grn->purchaseOrder->id,
                    'reference_no' => $grn->purchaseOrder->reference_no,
                    'drop_ship' => $grn->purchaseOrder->drop_ship,
                    'status' => $grn->purchaseOrder->status->value,
                    'can_receive_more' => ! $poFullyReceived && $grn->purchaseOrder->status->canReceive(),
                    'sale' => $grn->purchaseOrder->sale ? [
                        'id' => $grn->purchaseOrder->sale->id,
                        'invoice_no' => $grn->purchaseOrder->sale->invoice_no,
                    ] : null,
                ] : null,
                'is_virtual' => (bool) $grn->is_virtual,
                'warehouse' => $grn->warehouse ? ['name' => $grn->warehouse->name] : null,
                'items' => $grn->items->map(function ($item) use ($returnedByGrnItem) {
                    $qtyReceived = (float) $item->qty_received;
                    $qtyOrdered = (float) ($item->purchaseOrderItem?->qty_ordered ?? 0);
                    $qtyReturned = (float) ($returnedByGrnItem[$item->id] ?? 0);

                    return [
                        'id' => $item->id,
                        'product_variant_id' => $item->product_variant_id,
                        'purchase_order_item_id' => $item->purchase_order_item_id,
                        'qty_received' => $qtyReceived,
                        'qty_ordered' => $qtyOrdered,
                        'qty_received_on_po' => (float) ($item->purchaseOrderItem?->qty_received ?? 0),
                        'qty_returnable' => max(0, $qtyReceived - $qtyReturned),
                        'batch_no' => $item->batch_no,
                        'expiry_date' => $item->expiry_date?->toDateString(),
                        'variant' => $item->variant ? ['sku' => $item->variant->sku] : null,
                        'purchase_order_item' => $item->purchaseOrderItem ? [
                            'unit_price' => (float) $item->purchaseOrderItem->unit_price,
                        ] : null,
                    ];
                }),
                'supplier_invoices' => $grn->supplierInvoices->map(fn ($inv) => [
                    'id' => $inv->id,
                    'reference_no' => $inv->reference_no,
                    'status' => $inv->status->value,
                    'total' => number_format((float) $inv->total, 2, '.', ''),
                    'match_result' => $inv->matchResult ? [
                        'id' => $inv->matchResult->id,
                        'match_status' => $inv->matchResult->match_status->value,
                        'exception_reason' => $inv->matchResult->exception_reason,
                    ] : null,
                ]),
                'purchase_returns' => $grn->purchaseReturns->map(fn ($ret) => [
                    'id' => $ret->id,
                    'reference_no' => $ret->reference_no,
                    'status' => $ret->status->value,
                    'reason' => $ret->reason,
                    'debit_note' => $ret->debitNote ? [
                        'id' => $ret->debitNote->id,
                        'reference_no' => $ret->debitNote->reference_no,
                    ] : null,
                ]),
                'landed_cost_entries' => $grn->landedCostEntries->map(fn ($entry) => [
                    'id' => $entry->id,
                    'charge_type' => $entry->charge_type,
                    'description' => $entry->description,
                    'amount' => number_format((float) $entry->amount, 2, '.', ''),
                    'currency_code' => $entry->currency_code,
                    'allocation_method' => $entry->allocation_method->value,
                    'allocations' => $entry->allocations->map(fn ($a) => [
                        'grn_item_id' => $a->grn_item_id,
                        'allocated_amount' => (float) $a->allocated_amount,
                    ]),
                ]),
            ],
            'landedCostConfig' => [
                'charge_types' => $config['landed_cost_charge_types'] ?? [],
                'allocation_methods' => $config['landed_cost_allocation_methods'] ?? [],
            ],
            'branchId' => $branchId,
            'paymentMethods' => $config['payment_methods'] ?? ['cash', 'bank_transfer'],
            'warehouses' => Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }
}
