<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Inventory\AdjustStockData;
use App\DTOs\Inventory\ReceiveStockData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStockRequest;
use App\Http\Requests\Admin\QuarantineActionRequest;
use App\Http\Requests\Admin\ReceiveStockRequest;
use App\Models\BinLocation;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\InventoryService;
use App\Services\QuarantineService;
use App\Services\VariantBranchSettingService;
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
        private readonly QuarantineService $quarantineService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Inventory::class);

        $filters = ListPagination::filters(
            $request,
            ['search', 'warehouse_id', 'availability', 'quarantine', 'batch', 'bin', 'sort', 'direction'],
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

        $warehouses = $this->warehouseOptions($request);
        $warehouseIds = array_column($warehouses, 'id');

        $binsByWarehouse = BinLocation::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('is_active', true)
            ->orderBy('bin_code')
            ->get(['id', 'warehouse_id', 'bin_code', 'aisle', 'shelf'])
            ->groupBy('warehouse_id')
            ->map(fn ($bins) => $bins->map(fn (BinLocation $bin) => [
                'id' => $bin->id,
                'bin_code' => $bin->bin_code,
                'label' => trim($bin->bin_code.($bin->aisle ? " · {$bin->aisle}" : '')),
            ])->values()->all())
            ->all();

        return Inertia::render('Admin/Inventory/Receive', [
            'warehouses' => $warehouses,
            'binsByWarehouse' => $binsByWarehouse,
        ]);
    }

    public function receive(ReceiveStockRequest $request): RedirectResponse
    {
        $this->authorize('receive', Inventory::class);

        $data = ReceiveStockData::fromRequest($request);
        $this->inventoryService->receive($data);

        $message = $data->toQuarantine
            ? __('Stock received to quarantine successfully.')
            : __('Stock received successfully.');

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', $message);
    }

    public function binReport(Request $request): Response
    {
        $this->authorize('binReport', Inventory::class);

        $filters = ListPagination::filters(
            $request,
            ['search', 'warehouse_id', 'zone_id', 'sort', 'direction'],
        );
        $branchId = app(BranchContext::class)->branchId;

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        $paginator = $this->inventories
            ->paginateByBin(
                $filters,
                ListPagination::resolve($filters['per_page']),
            )
            ->through(fn ($row) => InventoryPresenter::row($row));

        return Inertia::render('Admin/Inventory/BinReport', [
            'inventory' => $paginator,
            'filters' => $filters,
            'warehouses' => $this->warehouseOptions($request),
        ]);
    }

    public function binTransferForm(Request $request): Response
    {
        $this->authorize('binTransfer', Inventory::class);

        $warehouses = $this->warehouseOptions($request);
        $warehouseIds = array_column($warehouses, 'id');

        $binsByWarehouse = BinLocation::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('is_active', true)
            ->orderBy('bin_code')
            ->get(['id', 'warehouse_id', 'bin_code', 'aisle', 'shelf'])
            ->groupBy('warehouse_id')
            ->map(fn ($bins) => $bins->map(fn (BinLocation $bin) => [
                'id' => $bin->id,
                'bin_code' => $bin->bin_code,
                'label' => trim($bin->bin_code.($bin->aisle ? " · {$bin->aisle}" : '')),
            ])->values()->all())
            ->all();

        return Inertia::render('Admin/Inventory/BinTransfer', [
            'warehouses' => $warehouses,
            'binsByWarehouse' => $binsByWarehouse,
        ]);
    }

    public function branchStockSettings(Request $request): Response
    {
        $this->authorize('branchStockSettings', Inventory::class);

        $branchId = app(BranchContext::class)->branchId;
        $filters = ListPagination::filters($request, ['search', 'sort', 'direction']);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = ProductVariant::query()
            ->with(['product', 'branchSettings' => fn ($q) => $q->when(
                $branchId !== null,
                fn ($inner) => $inner->where('branch_id', $branchId),
            )])
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('sku', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $term));
                });
            });

        $sort = $filters['sort'] ?? 'sku';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'product_name') {
            $query->join('products', 'product_variants.product_id', '=', 'products.id')
                ->orderBy('products.name', $direction)
                ->select('product_variants.*');
        } else {
            $query->orderBy('sku', $direction);
        }

        $paginator = $query
            ->paginate(ListPagination::resolve($filters['per_page']))
            ->withQueryString()
            ->through(function (ProductVariant $variant) use ($branchId) {
                $setting = $branchId !== null
                    ? $variant->branchSettings->firstWhere('branch_id', $branchId)
                    : null;

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->displayName(),
                    'product_name' => $variant->product?->name,
                    'default_reorder_point' => $variant->reorder_point,
                    'reorder_point' => $setting?->reorder_point,
                    'safety_stock_qty' => $setting?->safety_stock_qty,
                ];
            });

        return Inertia::render('Admin/Inventory/BranchStockSettings', [
            'variants' => $paginator,
            'filters' => $filters,
            'branchId' => $branchId,
        ]);
    }

    public function updateBranchStockSettings(Request $request): RedirectResponse
    {
        $this->authorize('branchStockSettings', Inventory::class);

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'safety_stock_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        app(VariantBranchSettingService::class)->upsert(
            branchId: (int) $validated['branch_id'],
            variantId: (int) $validated['product_variant_id'],
            reorderPoint: array_key_exists('reorder_point', $validated) && $validated['reorder_point'] !== ''
                ? (int) $validated['reorder_point']
                : null,
            safetyStockQty: array_key_exists('safety_stock_qty', $validated) && $validated['safety_stock_qty'] !== ''
                ? (int) $validated['safety_stock_qty']
                : null,
        );

        return redirect()
            ->back()
            ->with('success', __('Branch stock settings saved.'));
    }

    public function quarantineIndex(Request $request): Response
    {
        $this->authorize('releaseQuarantine', Inventory::class);

        $branchId = app(BranchContext::class)->branchId;
        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        $rows = Inventory::query()
            ->with(['warehouse.branch', 'variant.product', 'batch', 'binLocation'])
            ->where('quantity_in_quarantine', '>', 0)
            ->when($branchId !== null, fn ($q) => $q->whereHas(
                'warehouse',
                fn ($w) => $w->where('branch_id', $branchId),
            ))
            ->when(
                $accessibleIds !== null,
                fn ($q) => $q->whereHas(
                    'warehouse',
                    fn ($w) => $w->whereIn('branch_id', $accessibleIds),
                ),
            )
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn ($row) => array_merge(InventoryPresenter::row($row), [
                'quantity_in_quarantine' => $row->quantity_in_quarantine,
                'bin_code' => $row->binLocation?->bin_code,
            ]));

        return Inertia::render('Admin/Inventory/Quarantine', [
            'items' => $rows,
            'warehouses' => $this->warehouseOptions($request),
        ]);
    }

    public function releaseQuarantine(QuarantineActionRequest $request): RedirectResponse
    {
        $this->authorize('releaseQuarantine', Inventory::class);

        $validated = $request->validated();

        $this->quarantineService->release(
            warehouseId: (int) $validated['warehouse_id'],
            variantId: (int) $validated['product_variant_id'],
            batchId: isset($validated['batch_id']) ? (int) $validated['batch_id'] : null,
            binLocationId: isset($validated['bin_location_id']) ? (int) $validated['bin_location_id'] : null,
            quantity: (int) $validated['quantity'],
            userId: $request->user()?->id,
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('admin.inventory.quarantine')
            ->with('success', __('Stock released from quarantine.'));
    }

    public function scrapQuarantine(QuarantineActionRequest $request): RedirectResponse
    {
        $this->authorize('releaseQuarantine', Inventory::class);

        $validated = $request->validated();

        $this->quarantineService->scrap(
            warehouseId: (int) $validated['warehouse_id'],
            variantId: (int) $validated['product_variant_id'],
            batchId: isset($validated['batch_id']) ? (int) $validated['batch_id'] : null,
            binLocationId: isset($validated['bin_location_id']) ? (int) $validated['bin_location_id'] : null,
            quantity: (int) $validated['quantity'],
            userId: $request->user()?->id,
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('admin.inventory.quarantine')
            ->with('success', __('Quarantined stock scrapped.'));
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
            ->where('is_active', true)
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
