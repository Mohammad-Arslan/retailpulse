<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseReturnRequest;
use App\Models\GoodsReceivingNote;
use App\Models\PurchaseReturn;
use App\Services\Procurement\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly PurchaseReturnService $returns,
    ) {}

    public function store(StorePurchaseReturnRequest $request, GoodsReceivingNote $goodsReceivingNote): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.manage-returns'), 403);

        $return = $this->returns->create(
            $goodsReceivingNote,
            $request->validated('reason'),
            $request->validated('lines'),
            (int) $request->user()->id,
            $request->validated('notes'),
        );

        return back()->with('success', __('Purchase return :ref created.', ['ref' => $return->reference_no]));
    }

    public function approve(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.manage-returns'), 403);

        $this->returns->approve($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Return approved.'));
    }

    public function dispatch(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.manage-returns'), 403);

        $this->returns->dispatchGoods(
            $purchaseReturn,
            (int) $request->user()->id,
            (int) $request->validate(['warehouse_id' => ['required', 'integer', 'exists:warehouses,id']])['warehouse_id'],
        );

        return back()->with('success', __('Goods dispatched to supplier.'));
    }

    public function issueDebitNote(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.manage-returns'), 403);

        $debitNote = $this->returns->issueDebitNote($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Debit note :ref issued.', ['ref' => $debitNote->reference_no]));
    }
}
