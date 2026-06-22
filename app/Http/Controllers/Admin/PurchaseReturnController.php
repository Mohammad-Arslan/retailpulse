<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseReturnRequest;
use App\Models\DebitNote;
use App\Models\GoodsReceivingNote;
use App\Models\PurchaseReturn;
use App\Services\Procurement\DebitNotePdfService;
use App\Services\Procurement\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly PurchaseReturnService $returns,
        private readonly DebitNotePdfService $debitNotePdf,
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

    public function acknowledge(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.manage-returns'), 403);

        $this->returns->acknowledge($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Supplier acknowledged return.'));
    }

    public function issueDebitNote(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.manage-returns'), 403);

        $debitNote = $this->returns->issueDebitNote($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Debit note :ref issued.', ['ref' => $debitNote->reference_no]));
    }

    public function close(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.manage-returns'), 403);

        $this->returns->close($purchaseReturn, (int) $request->user()->id);

        return back()->with('success', __('Return closed.'));
    }

    public function debitNotePdf(DebitNote $debitNote): BinaryFileResponse
    {
        abort_unless(request()->user()?->can('procurement.view'), 403);

        $path = $this->debitNotePdf->generate($debitNote);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$debitNote->reference_no.'.pdf"',
        ]);
    }
}
