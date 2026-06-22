<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierPaymentRequest;
use App\Services\Procurement\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;

final class SupplierPaymentController extends Controller
{
    public function __construct(
        private readonly SupplierPaymentService $payments,
    ) {}

    public function store(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.process-payments'), 403);

        $payment = $this->payments->recordPayment(
            branchId: (int) $request->validated('branch_id'),
            supplierId: (int) $request->validated('supplier_id'),
            amount: (float) $request->validated('amount'),
            paymentMethod: $request->validated('payment_method'),
            currencyCode: $request->validated('currency_code'),
            exchangeRate: (float) $request->validated('exchange_rate', 1),
            paymentDate: $request->validated('payment_date'),
            userId: (int) $request->user()->id,
            invoiceId: $request->validated('supplier_invoice_id'),
            notes: $request->validated('notes'),
            isAdvance: (bool) $request->validated('is_advance', false),
        );

        return back()->with('success', __('Payment :ref recorded.', ['ref' => $payment->reference_no]));
    }
}
