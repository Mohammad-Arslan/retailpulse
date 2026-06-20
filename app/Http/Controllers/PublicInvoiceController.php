<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class PublicInvoiceController extends Controller
{
    public function show(string $publicToken): View|Response
    {
        $invoice = SaleInvoice::query()
            ->with(['sale.items', 'sale.branch', 'sale.cashier'])
            ->where('public_token', $publicToken)
            ->firstOrFail();

        if ($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path)) {
            return response(
                Storage::disk('local')->get($invoice->pdf_path),
                Response::HTTP_OK,
                ['Content-Type' => 'application/pdf'],
            );
        }

        $view = match ($invoice->template) {
            'thermal_80mm' => 'invoices.thermal_80mm',
            default => 'invoices.a4',
        };

        return view($view, [
            'invoice' => $invoice,
            'sale' => $invoice->sale,
        ]);
    }
}
