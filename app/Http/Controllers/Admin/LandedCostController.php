<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\LandedCostAllocationMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLandedCostRequest;
use App\Models\GoodsReceivingNote;
use App\Models\LandedCostEntry;
use App\Services\Procurement\LandedCostService;
use App\Services\Procurement\ProcurementConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LandedCostController extends Controller
{
    public function __construct(
        private readonly LandedCostService $landedCost,
        private readonly ProcurementConfigService $config,
    ) {}

    public function store(StoreLandedCostRequest $request, GoodsReceivingNote $goodsReceivingNote): RedirectResponse
    {
        $this->authorize('create', LandedCostEntry::class);

        $method = LandedCostAllocationMethod::from($request->validated('allocation_method'));

        $this->landedCost->allocate(
            $goodsReceivingNote,
            $request->validated('charge_type'),
            (float) $request->validated('amount'),
            $request->validated('currency_code'),
            (float) $request->validated('exchange_rate', 1),
            $method,
            (int) $request->user()->id,
            $request->validated('description'),
            $request->validated('manual_allocations', []),
        );

        return back()->with('success', __('Landed cost allocated.'));
    }

    public function destroy(Request $request, GoodsReceivingNote $goodsReceivingNote, LandedCostEntry $landedCostEntry): RedirectResponse
    {
        $this->authorize('delete', $landedCostEntry);

        if ($landedCostEntry->grn_id !== $goodsReceivingNote->id) {
            abort(404);
        }

        $landedCostEntry->allocations()->delete();
        $landedCostEntry->delete();

        return back()->with('success', __('Landed cost entry removed.'));
    }
}
