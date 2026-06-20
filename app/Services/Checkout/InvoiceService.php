<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\FbrInvoiceStatus;
use App\Models\FbrInvoiceQueue;
use App\Models\Sale;
use App\Models\SaleInvoice;
use App\Models\SystemSetting;
use Illuminate\Support\Str;

final class InvoiceService
{
    public function __construct(
        private readonly InvoiceNumberService $numbers,
        private readonly InvoicePdfService $pdf,
    ) {}

    public function createForSale(Sale $sale): SaleInvoice
    {
        $fbrEnabled = (bool) SystemSetting::get('fbr', 'enabled', false);
        $template = (string) SystemSetting::get('checkout', 'default_invoice_template', 'a4');
        $number = $this->numbers->generate($sale->branch_id, $sale->completed_at ?? now());

        $invoice = SaleInvoice::query()->create([
            'sale_id' => $sale->id,
            'number' => $number,
            'template' => $template,
            'public_token' => (string) Str::uuid(),
            'fbr_status' => $fbrEnabled ? FbrInvoiceStatus::Pending : FbrInvoiceStatus::NotApplicable,
        ]);

        $this->pdf->generate($invoice);

        if ($fbrEnabled) {
            FbrInvoiceQueue::query()->create([
                'sale_invoice_id' => $invoice->id,
                'status' => 'pending',
                'next_attempt_at' => now(),
            ]);
        }

        return $invoice->fresh();
    }

    public function regeneratePdf(SaleInvoice $invoice): SaleInvoice
    {
        $this->pdf->generate($invoice);

        return $invoice->fresh();
    }
}
