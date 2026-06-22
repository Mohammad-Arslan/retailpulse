<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\SupplierInvoice;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class SupplierInvoicePdfService
{
    public function generate(SupplierInvoice $invoice): string
    {
        $invoice->load([
            'supplier',
            'branch',
            'purchaseOrder',
            'grn',
            'items.variant.product',
            'matchResult',
        ]);

        $pdf = Pdf::loadView('procurement.supplier_invoice', [
            'invoice' => $invoice,
            'company' => [
                'legal_name' => SystemSetting::get('company', 'legal_name', config('app.name')),
                'address' => SystemSetting::get('company', 'address', ''),
                'phone' => SystemSetting::get('company', 'phone', ''),
                'email' => SystemSetting::get('company', 'email', ''),
            ],
            'generatedAt' => now(),
        ]);

        $path = 'procurement/inv-'.$invoice->reference_no.'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
