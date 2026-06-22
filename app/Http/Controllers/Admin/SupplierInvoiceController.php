<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierInvoiceRequest;
use App\Models\GoodsReceivingNote;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Services\Procurement\SupplierInvoicePdfService;
use App\Services\Procurement\SupplierInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SupplierInvoiceController extends Controller
{
    public function __construct(
        private readonly SupplierInvoiceService $invoices,
        private readonly SupplierInvoicePdfService $pdf,
    ) {}

    public function store(StoreSupplierInvoiceRequest $request, GoodsReceivingNote $goodsReceivingNote): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $invoice = $this->invoices->createFromGrn(
            $goodsReceivingNote,
            $request->validated('invoice_date'),
            $request->validated('due_date'),
            $request->validated('lines'),
            (int) $request->user()->id,
            $request->validated('notes'),
        );

        return back()->with('success', __('Supplier invoice :ref created.', ['ref' => $invoice->reference_no]));
    }

    public function approve(Request $request, SupplierInvoice $supplierInvoice): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $this->invoices->approve($supplierInvoice, (int) $request->user()->id);

        return back()->with('success', __('Invoice approved.'));
    }

    public function pdf(SupplierInvoice $supplierInvoice): BinaryFileResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $path = $this->pdf->generate($supplierInvoice);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$supplierInvoice->reference_no.'.pdf"',
        ]);
    }
}
