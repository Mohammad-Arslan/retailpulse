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
        $this->authorize('create', PurchaseReturn::class);

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
        $this->authorize('approve', $purchaseReturn);

        $this->returns->approve($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Return approved.'));
    }

    public function dispatch(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('dispatch', $purchaseReturn);

        $this->returns->dispatchGoods(
            $purchaseReturn,
            (int) $request->user()->id,
            (int) $request->validate(['warehouse_id' => ['required', 'integer', 'exists:warehouses,id']])['warehouse_id'],
        );

        return back()->with('success', __('Goods dispatched to supplier.'));
    }

    public function acknowledge(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('acknowledge', $purchaseReturn);

        $this->returns->acknowledge($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Supplier acknowledged return.'));
    }

    public function issueDebitNote(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('issueDebitNote', $purchaseReturn);

        $debitNote = $this->returns->issueDebitNote($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Debit note :ref issued.', ['ref' => $debitNote->reference_no]));
    }

    public function close(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('close', $purchaseReturn);

        $this->returns->close($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Return closed.'));
    }
}
