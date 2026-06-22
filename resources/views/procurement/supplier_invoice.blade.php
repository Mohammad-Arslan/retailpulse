<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Invoice {{ $invoice->reference_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .meta { margin-top: 12px; }
        .right { text-align: right; }
        .totals { margin-top: 12px; width: 280px; margin-left: auto; }
        .totals td { border: none; padding: 4px 8px; }
        .totals .label { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Supplier Invoice {{ $invoice->reference_no }}</h1>
    <div class="meta">
        <strong>{{ $company['legal_name'] }}</strong><br>
        @if ($company['address'])
            {{ $company['address'] }}<br>
        @endif
        Supplier: {{ $invoice->supplier?->name }} ({{ $invoice->supplier?->code }})<br>
        @if ($invoice->supplier?->tax_registration_no)
            Tax registration: {{ $invoice->supplier->tax_registration_no }}<br>
        @endif
        Branch: {{ $invoice->branch?->name }}<br>
        Invoice date: {{ $invoice->invoice_date?->format('Y-m-d') ?? '—' }}<br>
        Due date: {{ $invoice->effectiveDueDate()?->format('Y-m-d') ?? '—' }}<br>
        PO: {{ $invoice->purchaseOrder?->reference_no ?? '—' }}<br>
        GRN: {{ $invoice->grn?->reference_no ?? '—' }}<br>
        Status: {{ $invoice->status->label() }}<br>
        @if ($invoice->matchResult)
            Match: {{ $invoice->matchResult->match_status->label() }}
        @endif
    </div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Unit price</th>
                <th class="right">Tax %</th>
                <th class="right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->variant?->sku }}</td>
                    <td>{{ $item->description ?? $item->variant?->product?->name }}</td>
                    <td class="right">{{ $item->qty_invoiced }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->tax_rate, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="right">{{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency_code }}</td>
        </tr>
        @if ((float) $invoice->discount_total > 0)
            <tr>
                <td class="label">Discount</td>
                <td class="right">-{{ number_format((float) $invoice->discount_total, 2) }}</td>
            </tr>
        @endif
        @if ((float) $invoice->tax_total > 0)
            <tr>
                <td class="label">Tax</td>
                <td class="right">{{ number_format((float) $invoice->tax_total, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Total</td>
            <td class="right"><strong>{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</strong></td>
        </tr>
    </table>
    @if ($invoice->notes)
        <p><strong>Notes:</strong> {{ $invoice->notes }}</p>
    @endif
    <p style="margin-top:24px;font-size:10px;color:#666;">Generated {{ $generatedAt->format('Y-m-d H:i') }}</p>
</body>
</html>
