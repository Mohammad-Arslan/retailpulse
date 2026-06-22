<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Procurement\CreatePurchaseOrderData;
use App\DTOs\Procurement\PurchaseOrderLineData;
use App\DTOs\Procurement\ReceiveGrnData;
use App\DTOs\Procurement\ReceiveGrnLineData;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApprovePurchaseOrderRequest;
use App\Http\Requests\Admin\ReceiveGrnRequest;
use App\Http\Requests\Admin\RejectPurchaseOrderRequest;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Services\PosPinService;
use App\Services\Procurement\GoodsReceivingService;
use App\Services\Procurement\ProcurementConfigService;
use App\Services\Procurement\PurchaseOrderPdfService;
use App\Services\Procurement\PurchaseOrderService;
use App\Support\BranchContext;
use App\Support\BranchOperationalOptions;
use App\Support\ListPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $orders,
        private readonly PurchaseOrderService $orderService,
        private readonly GoodsReceivingService $grnService,
        private readonly ProcurementConfigService $config,
        private readonly PurchaseOrderPdfService $pdf,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'supplier_id', 'sort', 'direction']);
        $branchId = app(BranchContext::class)->branchId;

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        $paginator = $this->orders->paginate($filters, ListPagination::resolve($filters['per_page']));

        return Inertia::render('Admin/PurchaseOrders/Index', [
            'orders' => $paginator->through(fn (PurchaseOrder $o) => [
                'id' => $o->id,
                'reference_no' => $o->reference_no,
                'status' => $o->status->value,
                'is_historical' => (bool) $o->is_historical,
                'supplier' => $o->supplier ? ['id' => $o->supplier->id, 'name' => $o->supplier->name] : null,
                'total' => number_format((float) $o->total, 2, '.', ''),
                'expected_delivery_date' => $o->expected_delivery_date?->toDateString(),
                'created_at' => $o->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'statuses' => PurchaseOrderStatus::values(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        $branchId = app(BranchContext::class)->branchId;
        $config = $this->config->resolve($branchId);

        return Inertia::render('Admin/PurchaseOrders/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'currency_code']),
            'config' => $config,
            'branchId' => $branchId,
            'preselectedSupplierId' => $request->integer('supplier_id') ?: null,
            'preselectedSaleId' => $request->integer('sale_id') ?: null,
            'currencies' => BranchOperationalOptions::currencyOptions(),
            'defaultCurrency' => (string) ($config['default_currency'] ?? BranchOperationalOptions::defaultCurrency()),
        ]);
    }

    public function searchSales(Request $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $search = (string) $request->query('q', '');

        $sales = Sale::query()
            ->where('is_historical', false)
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term, $search) {
                    $q->where('invoice_no', 'like', $term);
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'invoice_no', 'total', 'status']);

        return response()->json($sales->map(fn (Sale $s) => [
            'id' => $s->id,
            'label' => ($s->invoice_no ?: 'Sale #'.$s->id).' — '.number_format((float) $s->total, 2),
        ]));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $lines = collect($request->validated('lines'))
            ->map(fn (array $line) => new PurchaseOrderLineData(
                variantId: (int) $line['product_variant_id'],
                qtyOrdered: (float) $line['qty_ordered'],
                unitPrice: (float) $line['unit_price'],
                priceOverrideReason: $line['price_override_reason'] ?? null,
                taxRate: (float) ($line['tax_rate'] ?? 0),
                description: $line['description'] ?? null,
            ))
            ->all();

        $order = $this->orderService->create(new CreatePurchaseOrderData(
            branchId: (int) $request->validated('branch_id'),
            supplierId: (int) $request->validated('supplier_id'),
            currencyCode: $request->validated('currency_code'),
            exchangeRate: (float) $request->validated('exchange_rate', 1),
            expectedDeliveryDate: $request->validated('expected_delivery_date'),
            notes: $request->validated('notes'),
            dropShip: (bool) $request->validated('drop_ship', false),
            saleId: $request->validated('sale_id'),
            userId: (int) $request->user()->id,
            lines: $lines,
        ));

        return redirect()->route('admin.purchase-orders.show', $order)
            ->with('success', __('Purchase order created.'));
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $order = $this->orders->findByIdWithRelations($purchaseOrder->id) ?? $purchaseOrder->load(['sale']);
        $branchId = app(BranchContext::class)->branchId;
        $user = $request->user();
        $posPin = app(PosPinService::class);

        return Inertia::render('Admin/PurchaseOrders/Show', [
            'order' => [
                'id' => $order->id,
                'branch_id' => $order->branch_id,
                'reference_no' => $order->reference_no,
                'status' => $order->status->value,
                'currency_code' => $order->currency_code,
                'total' => number_format((float) $order->total, 2, '.', ''),
                'drop_ship' => $order->drop_ship,
                'is_historical' => (bool) $order->is_historical,
                'sale_id' => $order->sale_id,
                'sale' => $order->sale ? [
                    'id' => $order->sale->id,
                    'invoice_no' => $order->sale->invoice_no,
                ] : null,
                'supplier' => $order->supplier ? ['id' => $order->supplier->id, 'name' => $order->supplier->name] : null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'qty_ordered' => (float) $item->qty_ordered,
                    'qty_received' => (float) $item->qty_received,
                    'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                    'variant' => $item->variant ? ['sku' => $item->variant->sku, 'name' => $item->variant->product?->name] : null,
                ]),
                'grns' => $order->grns->map(fn ($g) => [
                    'id' => $g->id,
                    'reference_no' => $g->reference_no,
                    'status' => $g->status->value,
                    'warehouse' => $g->warehouse ? ['name' => $g->warehouse->name] : null,
                ]),
                'supplier_invoices' => $order->supplierInvoices->map(fn ($inv) => [
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
            ],
            'config' => $this->config->resolve($branchId),
            'approval' => [
                'requiresPin' => $this->config->requiresApproval((float) $order->total, $order->branch_id),
                'approverHasPin' => $user !== null && $posPin->hasPin($user),
            ],
            'warehouses' => Warehouse::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('submit', $purchaseOrder);

        $this->orderService->submit($purchaseOrder, (int) $request->user()->id);

        return back()->with('success', __('Purchase order submitted for approval.'));
    }

    public function approve(ApprovePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);

        $this->orderService->approve(
            $purchaseOrder,
            $request->user(),
            $request->validated('manager_pin'),
        );

        return back()->with('success', __('Purchase order approved.'));
    }

    public function receive(ReceiveGrnRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('receive', $purchaseOrder);

        $lines = collect($request->validated('lines'))
            ->map(fn (array $line) => new ReceiveGrnLineData(
                purchaseOrderItemId: (int) $line['purchase_order_item_id'],
                qtyReceived: (float) $line['qty_received'],
                batchNo: $line['batch_no'] ?? null,
                expiryDate: $line['expiry_date'] ?? null,
                notes: $line['notes'] ?? null,
            ))
            ->all();

        $this->grnService->receive($purchaseOrder, new ReceiveGrnData(
            purchaseOrderId: $purchaseOrder->id,
            warehouseId: (int) $request->validated('warehouse_id'),
            userId: (int) $request->user()->id,
            notes: $request->validated('notes'),
            lines: $lines,
        ));

        return back()->with('success', __('Goods received successfully.'));
    }

    public function reject(RejectPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('reject', $purchaseOrder);

        $this->orderService->reject(
            $purchaseOrder,
            (int) $request->user()->id,
            $request->validated('rejection_reason'),
        );

        return back()->with('success', __('Purchase order rejected.'));
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $this->orderService->cancel($purchaseOrder, (int) $request->user()->id);

        return back()->with('success', __('Purchase order cancelled.'));
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('close', $purchaseOrder);

        $this->orderService->close($purchaseOrder, (int) $request->user()->id);

        return back()->with('success', __('Purchase order closed.'));
    }

    public function pdf(PurchaseOrder $purchaseOrder): BinaryFileResponse
    {
        $this->authorize('view', $purchaseOrder);
        $this->ensureSendable($purchaseOrder);

        $path = $this->pdf->generate($purchaseOrder->load(['supplier', 'branch', 'items.variant.product']));

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$purchaseOrder->reference_no.'.pdf"',
        ]);
    }

    public function email(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('view', $purchaseOrder);
        $this->ensureSendable($purchaseOrder);

        $purchaseOrder->load('supplier');
        $email = $purchaseOrder->supplier?->email;

        if ($email === null || trim($email) === '') {
            return back()->withErrors(['email' => __('Supplier has no email address.')]);
        }

        $path = $this->pdf->generate($purchaseOrder);

        Mail::raw(
            __('Please find purchase order :ref attached.', ['ref' => $purchaseOrder->reference_no]),
            function ($message) use ($email, $purchaseOrder, $path) {
                $message->to($email)
                    ->subject(__('Purchase Order :ref', ['ref' => $purchaseOrder->reference_no]))
                    ->attach(Storage::disk('local')->path($path), [
                        'as' => $purchaseOrder->reference_no.'.pdf',
                        'mime' => 'application/pdf',
                    ]);
            },
        );

        return back()->with('success', __('PO emailed to :email.', ['email' => $email]));
    }

    private function ensureSendable(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->is_historical) {
            return;
        }

        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Closed], true)) {
            abort(403, __('Purchase order must be approved before it can be sent to the supplier.'));
        }
    }
}
