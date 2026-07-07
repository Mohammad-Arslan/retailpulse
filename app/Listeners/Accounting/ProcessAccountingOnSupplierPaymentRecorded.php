<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\Procurement\SupplierPaymentRecorded;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Accounting\AccountingEventService;

final class ProcessAccountingOnSupplierPaymentRecorded
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
    ) {}

    public function handle(SupplierPaymentRecorded $event): void
    {
        $payment = $event->payment;

        $functionalAmount = (float) $payment->functional_amount;

        if ($functionalAmount <= 0) {
            return;
        }

        $userId = (int) ($payment->updated_by ?? $payment->created_by ?? 0);

        $this->accountingEvents->process(
            'payment.made',
            SupplierPayment::class,
            (int) $payment->id,
            [
                'date' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
                'branch_id' => $payment->branch_id,
                'currency_code' => $payment->currency_code,
                'exchange_rate' => (float) $payment->exchange_rate,
                'amount' => (float) $payment->amount,
                'settlement_amount' => $functionalAmount,
                'payment_method' => $payment->payment_method,
                'source_module' => 'procurement',
                'source_number' => $payment->reference_no,
                'description' => __('Supplier payment :ref', ['ref' => $payment->reference_no]),
                'party_type' => Supplier::class,
                'party_id' => $payment->supplier_id,
                'tax_direction' => 'purchase',
                'is_advance' => $payment->is_advance,
                'supplier_invoice_id' => $payment->supplier_invoice_id,
                'user_id' => $userId > 0 ? $userId : null,
            ],
            $userId,
        );
    }
}
