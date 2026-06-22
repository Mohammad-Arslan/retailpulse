<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\ProcurementDocumentType;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Events\Procurement\SupplierPaymentRecorded;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplierPaymentService
{
    public function __construct(
        private readonly ProcurementDocumentNumberService $documentNumbers,
        private readonly ProcurementConfigService $config,
        private readonly SupplierLedgerService $ledger,
    ) {}

    public function recordPayment(
        int $branchId,
        int $supplierId,
        float $amount,
        string $paymentMethod,
        string $currencyCode,
        float $exchangeRate,
        string $paymentDate,
        int $userId,
        ?int $invoiceId = null,
        ?string $notes = null,
        bool $isAdvance = false,
    ): SupplierPayment {
        $methods = $this->config->resolve($branchId)['payment_methods'];

        if (! in_array($paymentMethod, $methods, true)) {
            throw ValidationException::withMessages([
                'payment_method' => __('Payment method is not enabled.'),
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('Payment amount must be greater than zero.')]);
        }

        return DB::transaction(function () use (
            $branchId, $supplierId, $amount, $paymentMethod, $currencyCode,
            $exchangeRate, $paymentDate, $userId, $invoiceId, $notes, $isAdvance
        ) {
            if ($invoiceId !== null) {
                $invoice = SupplierInvoice::query()->findOrFail($invoiceId);
                $this->assertPayable($invoice);
            }

            $functionalAmount = round($amount * $exchangeRate, 2);

            $payment = SupplierPayment::query()->create([
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'supplier_invoice_id' => $invoiceId,
                'reference_no' => $this->documentNumbers->next($branchId, ProcurementDocumentType::SupplierPayment),
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'functional_amount' => $functionalAmount,
                'payment_date' => $paymentDate,
                'notes' => $notes,
                'is_advance' => $isAdvance,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->ledger->recordEntry(
                supplierId: $supplierId,
                branchId: $branchId,
                type: $isAdvance ? SupplierLedgerEntryType::Advance : SupplierLedgerEntryType::Payment,
                amount: $amount,
                currencyCode: $currencyCode,
                exchangeRate: $exchangeRate,
                functionalAmount: $functionalAmount,
                referenceType: SupplierPayment::class,
                referenceId: $payment->id,
                referenceNo: $payment->reference_no,
                notes: $notes,
                userId: $userId,
            );

            if ($invoiceId !== null) {
                SupplierInvoice::query()->where('id', $invoiceId)->update([
                    'status' => SupplierInvoiceStatus::Paid,
                    'updated_by' => $userId,
                ]);
            }

            event(new SupplierPaymentRecorded($payment));

            return $payment;
        });
    }

    private function assertPayable(SupplierInvoice $invoice): void
    {
        if (! $invoice->status->canPay()) {
            throw ValidationException::withMessages(['status' => __('Invoice is not payable in its current status.')]);
        }

        $match = $invoice->matchResult;

        if ($match !== null && ! $match->match_status->allowsPayment()) {
            throw ValidationException::withMessages([
                'match_status' => __('Invoice must be fully matched before payment.'),
            ]);
        }
    }
}
