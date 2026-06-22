<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceivingNote;
use App\Models\PurchaseOrder;
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
        $filters = ListPagination::filters($request, ['search']);

        $paginator = GoodsReceivingNote::query()
            ->with(['supplier', 'purchaseOrder', 'warehouse'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where('reference_no', 'like', $term);
            })
            ->orderByDesc('received_at')
            ->paginate(ListPagination::resolve($filters['per_page'] ?? 15))
            ->withQueryString();

        return Inertia::render('Admin/GoodsReceivingNotes/Index', [
            'grns' => $paginator->through(fn (GoodsReceivingNote $g) => [
                'id' => $g->id,
                'reference_no' => $g->reference_no,
                'status' => $g->status->value,
                'supplier' => $g->supplier?->name,
                'purchase_order' => $g->purchaseOrder?->reference_no,
                'warehouse' => $g->warehouse?->name,
                'received_at' => $g->received_at?->toIso8601String(),
            ]),
            'filters' => $filters,
        ]);
    }

    public function show(GoodsReceivingNote $goodsReceivingNote): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $grn = $goodsReceivingNote->load([
            'supplier',
            'purchaseOrder',
            'warehouse',
            'items.variant',
            'items.purchaseOrderItem',
            'supplierInvoices.matchResult',
            'purchaseReturns.debitNote',
        ]);

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
                'purchase_order' => $grn->purchaseOrder ? ['id' => $grn->purchaseOrder->id, 'reference_no' => $grn->purchaseOrder->reference_no] : null,
                'warehouse' => $grn->warehouse ? ['name' => $grn->warehouse->name] : null,
                'items' => $grn->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'purchase_order_item_id' => $item->purchase_order_item_id,
                    'qty_received' => (float) $item->qty_received,
                    'batch_no' => $item->batch_no,
                    'expiry_date' => $item->expiry_date?->toDateString(),
                    'variant' => $item->variant ? ['sku' => $item->variant->sku] : null,
                    'purchase_order_item' => $item->purchaseOrderItem ? [
                        'unit_price' => (float) $item->purchaseOrderItem->unit_price,
                    ] : null,
                ]),
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
                        'reference_no' => $ret->debitNote->reference_no,
                    ] : null,
                ]),
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
