<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Inventory\AdjustStockData;
use App\DTOs\Inventory\ReceiveStockData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStockRequest;
use App\Http\Requests\Admin\ReceiveStockRequest;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\InventoryService;
use App\Support\BranchContext;
use App\Support\InventoryPresenter;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventories,
        private readonly InventoryService $inventoryService,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Inventory::class);

        $filters = ListPagination::filters(
            $request,
            ['search', 'warehouse_id', 'sort', 'direction'],
        );
        $branchId = app(BranchContext::class)->branchId;

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        $paginator = $this->inventories
            ->paginateByWarehouse(
                $filters,
                ListPagination::resolve($filters['per_page']),
            )
            ->through(fn ($row) => InventoryPresenter::row($row));

        return Inertia::render('Admin/Inventory/Index', [
            'inventory' => $paginator,
            'filters' => $filters,
            'warehouses' => $this->warehouseOptions($request),
        ]);
    }

    public function adjustForm(Request $request): Response
    {
        $this->authorize('adjust', Inventory::class);

        return Inertia::render('Admin/Inventory/Adjust', [
            'warehouses' => $this->warehouseOptions($request),
            'reasons' => [
                ['value' => 'adjustment', 'label' => 'Adjustment'],
                ['value' => 'damaged', 'label' => 'Damaged'],
            ],
        ]);
    }

    public function adjust(AdjustStockRequest $request): RedirectResponse
    {
        $this->authorize('adjust', Inventory::class);

        $this->inventoryService->adjust(AdjustStockData::fromRequest($request));

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', __('Stock adjusted successfully.'));
    }

    public function receiveForm(Request $request): Response
    {
        $this->authorize('receive', Inventory::class);

        return Inertia::render('Admin/Inventory/Receive', [
            'warehouses' => $this->warehouseOptions($request),
        ]);
    }

    public function receive(ReceiveStockRequest $request): RedirectResponse
    {
        $this->authorize('receive', Inventory::class);

        $this->inventoryService->receive(ReceiveStockData::fromRequest($request));

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', __('Stock received successfully.'));
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
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when(
                $accessibleIds !== null,
                fn ($q) => $q->whereIn('branch_id', $accessibleIds),
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
