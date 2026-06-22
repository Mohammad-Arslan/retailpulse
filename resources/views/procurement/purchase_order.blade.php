<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $order->reference_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .meta { margin-top: 12px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Purchase Order {{ $order->reference_no }}</h1>
    <div class="meta">
        <strong>{{ $company['legal_name'] }}</strong><br>
        Supplier: {{ $order->supplier?->name }} ({{ $order->supplier?->code }})<br>
        Branch: {{ $order->branch?->name }}<br>
        Date: {{ $order->created_at?->format('Y-m-d') }}<br>
        Expected delivery: {{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}<br>
        Status: {{ $order->status->value }}
    </div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Unit price</th>
                <th class="right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->variant?->sku }}</td>
                    <td>{{ $item->description ?? $item->variant?->product?->name }}</td>
                    <td class="right">{{ $item->qty_ordered }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="right">Total ({{ $order->currency_code }})</th>
                <th class="right">{{ number_format((float) $order->total, 2) }}</th>
            </tr>
        </tfoot>
    </table>
    @if ($order->notes)
        <p><strong>Notes:</strong> {{ $order->notes }}</p>
    @endif
    <p style="margin-top:24px;font-size:10px;color:#666;">Generated {{ $generatedAt->format('Y-m-d H:i') }}</p>
</body>
</html>
