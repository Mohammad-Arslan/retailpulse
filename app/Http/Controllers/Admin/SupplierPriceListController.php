<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierPriceListRequest;
use App\Http\Requests\Admin\UpdateSupplierPriceListRequest;
use App\Models\Supplier;
use App\Models\SupplierPriceList;
use App\Models\SystemSetting;
use App\Services\Procurement\SupplierPriceListService;
use App\Support\BranchOperationalOptions;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierPriceListController extends Controller
{
    public function __construct(
        private readonly SupplierPriceListService $priceLists,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $filters = ListPagination::filters($request, ['search', 'supplier_id']);

        $query = SupplierPriceList::query()
            ->with('supplier')
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
                });
            })
            ->orderByDesc('valid_from');

        $lists = $query->paginate(ListPagination::resolve($filters['per_page'] ?? 15))->withQueryString();

        $expiryAlertDays = (int) SystemSetting::get('procurement', 'price_list_expiry_alert_days', 30);
        $expiryThreshold = now()->addDays($expiryAlertDays)->toDateString();
        $today = now()->toDateString();

        return Inertia::render('Admin/SupplierPriceLists/Index', [
            'priceLists' => $lists->through(function (SupplierPriceList $list) use ($expiryThreshold, $today) {
                $validTo = $list->valid_to?->toDateString();
                $expiringSoon = $list->is_active
                    && $validTo !== null
                    && $validTo >= $today
                    && $validTo <= $expiryThreshold;

                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'supplier' => $list->supplier?->name,
                    'supplier_id' => $list->supplier_id,
                    'valid_from' => $list->valid_from?->toDateString(),
                    'valid_to' => $validTo,
                    'currency_code' => $list->currency_code,
                    'is_active' => $list->is_active,
                    'items_count' => $list->items()->count(),
                    'expiring_soon' => $expiringSoon,
                ];
            }),
            'filters' => $filters,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'expiryAlertDays' => $expiryAlertDays,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Supplier::class);

        return Inertia::render('Admin/SupplierPriceLists/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'currency_code']),
            'preselectedSupplierId' => $request->integer('supplier_id') ?: null,
            'currencies' => BranchOperationalOptions::currencyOptions(),
        ]);
    }

    public function store(StoreSupplierPriceListRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::query()->findOrFail($request->validated('supplier_id'));

        $list = $this->priceLists->create(
            $supplier,
            $request->safe()->except('items'),
            $request->validated('items', []),
            (int) $request->user()->id,
        );

        return redirect()->route('admin.supplier-price-lists.edit', $list)
            ->with('success', __('Price list created.'));
    }

    public function edit(SupplierPriceList $supplierPriceList): Response
    {
        $this->authorize('view', $supplierPriceList->supplier);

        $list = $supplierPriceList->load(['supplier', 'items.variant.product']);

        return Inertia::render('Admin/SupplierPriceLists/Edit', [
            'priceList' => [
                'id' => $list->id,
                'supplier_id' => $list->supplier_id,
                'supplier' => $list->supplier?->only(['id', 'name']),
                'name' => $list->name,
                'valid_from' => $list->valid_from?->toDateString(),
                'valid_to' => $list->valid_to?->toDateString(),
                'currency_code' => $list->currency_code,
                'is_active' => $list->is_active,
                'items' => $list->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $item->variant?->sku,
                    'product_name' => $item->variant?->product?->name,
                    'unit_price' => (float) $item->unit_price,
                    'min_qty' => (float) $item->min_qty,
                    'lead_time_days' => $item->lead_time_days,
                ]),
            ],
            'currencies' => BranchOperationalOptions::currencyOptions(),
        ]);
    }

    public function update(UpdateSupplierPriceListRequest $request, SupplierPriceList $supplierPriceList): RedirectResponse
    {
        $this->authorize('update', $supplierPriceList->supplier);

        $this->priceLists->update(
            $supplierPriceList,
            $request->safe()->except('items'),
            $request->validated('items', []),
            (int) $request->user()->id,
        );

        return back()->with('success', __('Price list updated.'));
    }

    public function destroy(SupplierPriceList $supplierPriceList): RedirectResponse
    {
        $this->authorize('update', $supplierPriceList->supplier);

        $this->priceLists->delete($supplierPriceList);

        return redirect()->route('admin.supplier-price-lists.index')
            ->with('success', __('Price list deleted.'));
    }
}
