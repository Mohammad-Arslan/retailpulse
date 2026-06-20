<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SaleExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorize('sales.export');

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
        ]);

        $query = Sale::query()
            ->with(['branch', 'cashier', 'invoice'])
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->query('branch_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('created_at', 'desc');

        $filename = 'sales-export-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'Invoice Number', 'Branch', 'Cashier',
                'Status', 'Subtotal', 'Discount', 'Tax', 'Grand Total',
                'Currency', 'Balance Due', 'Tax Mode', 'Is Historical',
                'Completed At', 'Created At',
            ]);

            $query->chunk(500, function ($sales) use ($handle) {
                foreach ($sales as $sale) {
                    fputcsv($handle, [
                        $sale->id,
                        $sale->invoice?->number ?? '',
                        $sale->branch?->name ?? $sale->branch_id,
                        $sale->cashier?->name ?? $sale->cashier_id,
                        $sale->status?->value ?? '',
                        number_format((float) $sale->subtotal, 2, '.', ''),
                        number_format((float) $sale->total_discount, 2, '.', ''),
                        number_format((float) $sale->tax_total, 2, '.', ''),
                        number_format((float) $sale->grand_total, 2, '.', ''),
                        $sale->currency,
                        number_format((float) $sale->balance_due, 2, '.', ''),
                        $sale->tax_mode?->value ?? '',
                        $sale->is_historical ? 'yes' : 'no',
                        $sale->completed_at?->toDateTimeString() ?? '',
                        $sale->created_at?->toDateTimeString() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
