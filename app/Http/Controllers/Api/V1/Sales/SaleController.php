<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\DTOs\Checkout\AddPaymentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Checkout\AddPaymentRequest;
use App\Models\Sale;
use App\Models\SaleInvoice;
use App\Services\Checkout\CheckoutService;
use App\Services\Checkout\InvoiceService;
use App\Services\Checkout\SalePaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class SaleController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly InvoiceService $invoices,
        private readonly SalePaymentProcessor $paymentProcessor,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()
            ->with(['items', 'payments', 'invoice', 'customer', 'cashier', 'branch'])
            ->findOrFail($id);

        return response()->json($this->formatSaleDetail($sale));
    }

    public function addPayment(AddPaymentRequest $request, int $id): JsonResponse
    {
        $sale = Sale::query()->findOrFail($id);

        if ($sale->cashier_id !== $request->user()->id && ! $request->user()->can('sales.view')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        try {
            $sale = $this->checkout->addPayment(
                sale: $sale,
                data: new AddPaymentData(
                    method: $request->validated('method'),
                    amount: (float) ($request->validated('amount') ?? 0),
                    tenderedAmount: $request->validated('tendered_amount') !== null
                        ? (float) $request->validated('tendered_amount')
                        : null,
                    meta: $request->validated('meta') ?? [],
                ),
                cashierId: $request->user()->id,
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        $lastPayment = $sale->payments->sortByDesc('id')->first();
        $response = $this->formatSaleDetail($sale);

        if ($lastPayment !== null && $lastPayment->status?->value === 'failed') {
            $response['payment_error'] = $this->paymentProcessor->userFriendlyError($lastPayment->gateway_response);
        }

        return response()->json($response);
    }

    public function void(Request $request, int $id): JsonResponse
    {
        $this->authorize('pos.void-cart');

        $sale = Sale::query()->findOrFail($id);
        $this->checkout->voidSale($sale);

        return response()->json(['message' => __('Sale voided.')]);
    }

    public function invoice(Request $request, int $id): JsonResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()->with('invoice')->findOrFail($id);

        if ($sale->invoice === null) {
            return response()->json(['message' => __('Invoice not yet generated.')], Response::HTTP_NOT_FOUND);
        }

        return response()->json($this->formatInvoice($sale->invoice));
    }

    public function generatePdf(Request $request, int $id): JsonResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()->with('invoice')->findOrFail($id);

        if ($sale->invoice === null) {
            throw ValidationException::withMessages([
                'invoice' => __('Invoice not yet generated.'),
            ]);
        }

        $invoice = $this->invoices->regeneratePdf($sale->invoice);

        return response()->json($this->formatInvoice($invoice));
    }

    public function share(Request $request, int $id): JsonResponse
    {
        $this->authorize('sales.view');

        $request->validate([
            'method' => ['required', 'in:email,link,whatsapp,print'],
        ]);

        $sale = Sale::query()->with('invoice')->findOrFail($id);

        if ($sale->invoice === null) {
            throw ValidationException::withMessages([
                'invoice' => __('Invoice not yet generated.'),
            ]);
        }

        return response()->json([
            'method' => $request->input('method'),
            'public_url' => route('invoice.public', $sale->invoice->public_token),
            'pdf_path' => $sale->invoice->pdf_path,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSaleDetail(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'status' => $sale->status?->value,
            'balance_due' => number_format((float) $sale->balance_due, 2, '.', ''),
            'grand_total' => number_format((float) $sale->grand_total, 2, '.', ''),
            'subtotal' => number_format((float) $sale->subtotal, 2, '.', ''),
            'tax_total' => number_format((float) $sale->tax_total, 2, '.', ''),
            'total_discount' => number_format((float) $sale->total_discount, 2, '.', ''),
            'currency' => $sale->currency,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
            ] : null,
            'items' => $sale->items->map(fn ($item) => [
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'tax_amount' => number_format((float) $item->tax_amount, 2, '.', ''),
                'line_total_inc_tax' => number_format((float) $item->line_total_inc_tax, 2, '.', ''),
            ])->all(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method?->value,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'status' => $payment->status?->value,
                'meta' => $payment->meta,
            ])->all(),
            'invoice' => $sale->invoice ? $this->formatInvoice($sale->invoice) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInvoice(SaleInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'template' => $invoice->template,
            'pdf_path' => $invoice->pdf_path,
            'public_token' => $invoice->public_token,
            'fbr_status' => $invoice->fbr_status?->value,
            'download_url' => $invoice->pdf_path
                ? Storage::disk('local')->url($invoice->pdf_path)
                : null,
        ];
    }
}
