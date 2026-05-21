<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\StockTransfer\CreateStockTransferData;
use App\Enums\StockTransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockTransferRequest;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\StockTransferService;
use App\Support\BranchContext;
use App\Support\ListPagination;
use App\Support\StockTransferPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferRepositoryInterface $transfers,
        private readonly StockTransferService $transferService,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockTransfer::class);

        $filters = ListPagination::filters($request, ['search', 'status']);

        $branchId = app(BranchContext::class)->branchId;

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        $paginator = $this->transfers->paginate(
            $filters,
            ListPagination::resolve($filters['per_page']),
        );
        $paginator->getCollection()->transform(
            fn (StockTransfer $transfer) => StockTransferPresenter::summary($transfer),
        );

        return Inertia::render('Admin/StockTransfers/Index', [
            'transfers' => $paginator,
            'filters' => $filters,
            'statuses' => StockTransferStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', StockTransfer::class);

        return Inertia::render('Admin/StockTransfers/Create', [
            'warehouses' => $this->warehouseOptions($request),
        ]);
    }

    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $this->authorize('create', StockTransfer::class);

        $transfer = $this->transferService->create(
            CreateStockTransferData::fromRequest($request),
        );

        return redirect()
            ->route('admin.stock-transfers.show', $transfer)
            ->with('success', __('Stock transfer created.'));
    }

    public function show(StockTransfer $stockTransfer): Response
    {
        $this->authorize('view', $stockTransfer);

        $transfer = $this->transfers->findByIdWithRelations($stockTransfer->id) ?? $stockTransfer;

        return Inertia::render('Admin/StockTransfers/Show', [
            'transfer' => StockTransferPresenter::detail($transfer),
        ]);
    }

    public function ship(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('ship', $stockTransfer);

        $this->transferService->ship($stockTransfer, (int) $request->user()->id);

        return redirect()
            ->route('admin.stock-transfers.show', $stockTransfer)
            ->with('success', __('Transfer marked as shipped.'));
    }

    public function receive(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('receive', $stockTransfer);

        $this->transferService->receive($stockTransfer, (int) $request->user()->id);

        return redirect()
            ->route('admin.stock-transfers.show', $stockTransfer)
            ->with('success', __('Transfer received into destination warehouse.'));
    }

    /**
     * @return list<array{id: int, name: string, code: string, branch_name: string}>
     */
    private function warehouseOptions(Request $request): array
    {
        $branchId = app(BranchContext::class)->branchId;
        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        return Warehouse::query()
            ->with('branch')
            ->when(
                $accessibleIds !== null,
                fn ($q) => $q->whereIn('branch_id', $accessibleIds),
            )
            ->when(
                $branchId === null && $accessibleIds === null,
                fn ($q) => $q,
            )
            ->orderBy('name')
            ->get()
            ->map(fn (Warehouse $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
                'branch_name' => $w->branch?->name ?? '',
            ])
            ->values()
            ->all();
    }
}
