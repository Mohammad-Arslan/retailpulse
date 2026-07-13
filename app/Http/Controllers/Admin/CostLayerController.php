<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreCostLayerRequest;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Accounting\CostService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

final class CostLayerController extends Controller
{
    public function __construct(
        private readonly CostService $costService,
    ) {}

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('accounting.manage-fiscal-years'), 403);

        return Inertia::render('Admin/Accounting/CostLayers/Create', [
            'warehouses' => Warehouse::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'variants' => ProductVariant::query()
                ->with('product:id,name')
                ->whereHas('product', fn ($q) => $q->where('is_active', true))
                ->orderBy('sku')
                ->get(['id', 'sku', 'name', 'product_id'])
                ->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'product_name' => $variant->product?->name,
                ])
                ->values(),
        ]);
    }

    public function store(StoreCostLayerRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage-fiscal-years'), 403);

        $validated = $request->validated();
        $receivedAt = isset($validated['received_at'])
            ? Carbon::parse($validated['received_at'])
            : now();

        $layer = $this->costService->createLayerOnReceive(
            productVariantId: (int) $validated['product_variant_id'],
            warehouseId: (int) $validated['warehouse_id'],
            qtyReceived: (float) $validated['qty'],
            unitCost: (float) $validated['unit_cost'],
            sourceReferenceType: 'manual_admin_entry',
            sourceReferenceId: (int) $request->user()->id,
            batchNo: $validated['batch_no'] ?? null,
            receivedAt: $receivedAt,
        );

        Log::info('Manual cost layer created by admin', [
            'admin_user_id' => (int) $request->user()->id,
            'inventory_cost_layer_id' => $layer->id,
            'reason' => $validated['reason'],
        ]);

        return redirect()
            ->route('admin.accounting.cost-layers.create')
            ->with('success', __('Cost layer created successfully.'));
    }
}
