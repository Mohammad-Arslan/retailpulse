<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Procurement\CreatePurchaseRequestData;
use App\DTOs\Procurement\PurchaseRequestLineData;
use App\Enums\PurchaseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApprovePurchaseRequestRequest;
use App\Http\Requests\Admin\ConvertPurchaseRequestRequest;
use App\Http\Requests\Admin\RejectPurchaseRequestRequest;
use App\Http\Requests\Admin\StorePurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use App\Services\PosPinService;
use App\Services\Procurement\ProcurementConfigService;
use App\Services\Procurement\PurchaseRequestService;
use App\Support\BranchContext;
use App\Support\BranchOperationalOptions;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestRepositoryInterface $requests,
        private readonly PurchaseRequestService $requestService,
        private readonly ProcurementConfigService $config,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureFeatureEnabled();
        $this->authorize('viewAny', PurchaseRequest::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $branchId = app(BranchContext::class)->branchId;

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        $paginator = $this->requests->paginate($filters, ListPagination::resolve($filters['per_page']));

        return Inertia::render('Admin/PurchaseRequests/Index', [
            'requests' => $paginator->through(fn (PurchaseRequest $r) => [
                'id' => $r->id,
                'reference_no' => $r->reference_no,
                'status' => $r->status->value,
                'branch' => $r->branch ? ['id' => $r->branch->id, 'name' => $r->branch->name] : null,
                'total' => number_format((float) $r->total, 2, '.', ''),
                'needed_by' => $r->needed_by?->toDateString(),
                'created_at' => $r->created_at?->toIso8601String(),
                'items_count' => $r->items_count ?? $r->items()->count(),
            ]),
            'filters' => $filters,
            'statuses' => PurchaseRequestStatus::values(),
        ]);
    }

    public function create(): Response
    {
        $this->ensureFeatureEnabled();
        $this->authorize('create', PurchaseRequest::class);

        $branchId = app(BranchContext::class)->branchId;
        $config = $this->config->resolve($branchId);

        return Inertia::render('Admin/PurchaseRequests/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'config' => $config,
            'branchId' => $branchId,
            'currencies' => BranchOperationalOptions::currencyOptions(),
            'defaultCurrency' => (string) ($config['default_currency'] ?? BranchOperationalOptions::defaultCurrency()),
        ]);
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        $this->ensureFeatureEnabled();
        $this->authorize('create', PurchaseRequest::class);

        $lines = collect($request->validated('lines'))
            ->map(fn (array $line) => new PurchaseRequestLineData(
                variantId: (int) $line['product_variant_id'],
                qty: (float) $line['qty'],
                estimatedUnitCost: (float) $line['estimated_unit_cost'],
                unitId: isset($line['unit_id']) ? (int) $line['unit_id'] : null,
                preferredSupplierId: isset($line['preferred_supplier_id']) ? (int) $line['preferred_supplier_id'] : null,
                notes: $line['notes'] ?? null,
            ))
            ->all();

        $purchaseRequest = $this->requestService->create(new CreatePurchaseRequestData(
            branchId: (int) $request->validated('branch_id'),
            warehouseId: $request->validated('warehouse_id') !== null ? (int) $request->validated('warehouse_id') : null,
            currencyCode: $request->validated('currency_code'),
            exchangeRate: (float) $request->validated('exchange_rate', 1),
            neededBy: $request->validated('needed_by'),
            notes: $request->validated('notes'),
            userId: (int) $request->user()->id,
            lines: $lines,
        ));

        return redirect()->route('admin.purchase-requests.show', $purchaseRequest)
            ->with('success', __('Purchase request created.'));
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): Response
    {
        $this->ensureFeatureEnabled();
        $this->authorize('view', $purchaseRequest);

        $model = $this->requests->findByIdWithRelations($purchaseRequest->id) ?? $purchaseRequest;
        $branchId = app(BranchContext::class)->branchId;
        $user = $request->user();
        $posPin = app(PosPinService::class);

        return Inertia::render('Admin/PurchaseRequests/Show', [
            'purchaseRequest' => [
                'id' => $model->id,
                'branch_id' => $model->branch_id,
                'reference_no' => $model->reference_no,
                'status' => $model->status->value,
                'currency_code' => $model->currency_code,
                'total' => number_format((float) $model->total, 2, '.', ''),
                'subtotal' => number_format((float) $model->subtotal, 2, '.', ''),
                'needed_by' => $model->needed_by?->toDateString(),
                'notes' => $model->notes,
                'rejection_reason' => $model->rejection_reason,
                'warehouse' => $model->warehouse ? ['id' => $model->warehouse->id, 'name' => $model->warehouse->name] : null,
                'converted_purchase_order' => $model->convertedPurchaseOrder ? [
                    'id' => $model->convertedPurchaseOrder->id,
                    'reference_no' => $model->convertedPurchaseOrder->reference_no,
                ] : null,
                'items' => $model->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'qty' => (float) $item->qty,
                    'estimated_unit_cost' => number_format((float) $item->estimated_unit_cost, 2, '.', ''),
                    'line_total' => number_format((float) $item->line_total, 2, '.', ''),
                    'notes' => $item->notes,
                    'preferred_supplier' => $item->preferredSupplier
                        ? ['id' => $item->preferredSupplier->id, 'name' => $item->preferredSupplier->name]
                        : null,
                    'variant' => $item->variant ? [
                        'sku' => $item->variant->sku,
                        'name' => $item->variant->product?->name,
                    ] : null,
                ]),
            ],
            'config' => $this->config->resolve($branchId),
            'approval' => [
                'requiresPin' => $this->config->requiresPrApproval((float) $model->total, $model->branch_id),
                'approverHasPin' => $user !== null && $posPin->hasPin($user),
            ],
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can' => [
                'submit' => $user?->can('submit', $model) ?? false,
                'approve' => $user?->can('approve', $model) ?? false,
                'reject' => $user?->can('reject', $model) ?? false,
                'cancel' => $user?->can('cancel', $model) ?? false,
                'convert' => $user?->can('convert', $model) ?? false,
            ],
        ]);
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->ensureFeatureEnabled();
        $this->authorize('submit', $purchaseRequest);

        $this->requestService->submit($purchaseRequest, (int) $request->user()->id);

        return back()->with('success', __('Purchase request submitted for approval.'));
    }

    public function approve(ApprovePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->ensureFeatureEnabled();
        $this->authorize('approve', $purchaseRequest);

        $this->requestService->approve(
            $purchaseRequest,
            $request->user(),
            $request->validated('manager_pin'),
        );

        return back()->with('success', __('Purchase request approved.'));
    }

    public function reject(RejectPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->ensureFeatureEnabled();
        $this->authorize('reject', $purchaseRequest);

        $this->requestService->reject(
            $purchaseRequest,
            (int) $request->user()->id,
            $request->validated('rejection_reason'),
        );

        return back()->with('success', __('Purchase request rejected.'));
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->ensureFeatureEnabled();
        $this->authorize('cancel', $purchaseRequest);

        $this->requestService->cancel($purchaseRequest, (int) $request->user()->id);

        return back()->with('success', __('Purchase request cancelled.'));
    }

    public function convert(ConvertPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->ensureFeatureEnabled();
        $this->authorize('convert', $purchaseRequest);

        $order = $this->requestService->convertToPurchaseOrder(
            $purchaseRequest,
            (int) $request->validated('supplier_id'),
            (int) $request->user()->id,
        );

        return redirect()->route('admin.purchase-orders.show', $order)
            ->with('success', __('Purchase request converted to a draft purchase order.'));
    }

    private function ensureFeatureEnabled(): void
    {
        if (! $this->config->purchaseRequestsEnabled()) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }
    }
}
