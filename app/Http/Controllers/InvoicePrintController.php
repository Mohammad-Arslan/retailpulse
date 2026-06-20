<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SystemSetting;
use Illuminate\View\View;

final class InvoicePrintController extends Controller
{
    public function show(string $publicToken): View
    {
        $invoice = SaleInvoice::query()
            ->with(['sale.items', 'sale.branch', 'sale.cashier', 'sale.customer', 'sale.payments'])
            ->where('public_token', $publicToken)
            ->firstOrFail();

        $view = match ($invoice->template) {
            'thermal_80mm' => 'invoices.thermal_80mm',
            default => 'invoices.a4',
        };

        return view($view, [
            'invoice' => $invoice,
            'sale' => $invoice->sale,
            'company' => [
                'name' => (string) SystemSetting::get('company', 'legal_name', ''),
                'address' => (string) SystemSetting::get('company', 'address', ''),
                'phone' => (string) SystemSetting::get('company', 'phone', ''),
                'email' => (string) SystemSetting::get('company', 'email', ''),
                'tax_id' => (string) SystemSetting::get('company', 'tax_id', ''),
            ],
            'tax_enabled' => (bool) SystemSetting::get('tax', 'enabled', true),
            'fbr_enabled' => (bool) SystemSetting::get('fbr', 'enabled', false),
            'auto_print' => true,
        ]);
    }
}
