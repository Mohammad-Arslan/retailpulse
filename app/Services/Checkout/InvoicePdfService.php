<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\SaleInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class InvoicePdfService
{
    public function generate(SaleInvoice $invoice): string
    {
        $invoice->load(['sale.items', 'sale.branch', 'sale.cashier', 'sale.customer', 'sale.payments']);

        $view = match ($invoice->template) {
            'thermal_80mm' => 'invoices.thermal_80mm',
            default => 'invoices.a4',
        };

        $pdf = Pdf::loadView($view, [
            'invoice' => $invoice,
            'sale' => $invoice->sale,
        ]);

        $path = 'invoices/'.$invoice->number.'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }
}
