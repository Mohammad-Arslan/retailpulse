<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Enums\PoMatchStatus;
use App\Events\Procurement\SupplierInvoiceMatched;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Accounting\AccountingEventService;

final class ProcessAccountingOnSupplierInvoiceMatched
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
    ) {}

    public function handle(SupplierInvoiceMatched $event): void
    {
        if ($event->matchResult->match_status !== PoMatchStatus::FullyMatched) {
            return;
        }

        $invoice = $event->invoice;
        $invoice->loadMissing(['purchaseOrder', 'supplier', 'grn']);

        $exchangeRate = (float) $invoice->exchange_rate;
        $functionalGross = (float) $invoice->functional_total;
        $functionalNet = round((float) $invoice->subtotal * $exchangeRate, 2);
        $functionalTax = round((float) $invoice->tax_total * $exchangeRate, 2);

        if ($functionalGross <= 0) {
            return;
        }

        $userId = (int) ($invoice->updated_by ?? $invoice->created_by ?? 0);

        $this->accountingEvents->process(
            'purchase.invoice_posted',
            SupplierInvoice::class,
            (int) $invoice->id,
            [
                'date' => $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
                'branch_id' => $invoice->branch_id,
                'warehouse_id' => $invoice->grn?->warehouse_id,
                'currency_code' => $invoice->currency_code,
                'exchange_rate' => $exchangeRate,
                'gross_amount' => $functionalGross,
                'net_amount' => $functionalNet,
                'tax_amount' => $functionalTax,
                'discount_amount' => round((float) $invoice->discount_total * $exchangeRate, 2),
                'source_module' => 'procurement',
                'source_number' => $invoice->reference_no,
                'description' => __('Supplier invoice :ref', ['ref' => $invoice->reference_no]),
                'party_type' => Supplier::class,
                'party_id' => $invoice->supplier_id,
                'tax_direction' => 'purchase',
                'user_id' => $userId > 0 ? $userId : null,
            ],
            $userId,
        );
    }
}
