<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Statement — {{ $supplier->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Supplier Statement</h1>
    <p>
        <strong>{{ $supplier->name }}</strong> ({{ $supplier->code }})<br>
        {{ $company['legal_name'] }}<br>
        @if ($from || $to)
            Period: {{ $from ?? 'start' }} — {{ $to ?? 'now' }}<br>
        @endif
        Outstanding balance: <strong>{{ number_format($balance, 2) }} {{ $supplier->currency_code }}</strong>
    </p>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reference</th>
                <th class="right">Amount</th>
                <th class="right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->created_at?->format('Y-m-d') }}</td>
                    <td>{{ $entry->entry_type->value }}</td>
                    <td>{{ $entry->reference_no }}</td>
                    <td class="right">{{ number_format((float) $entry->amount, 2) }}</td>
                    <td class="right">{{ number_format((float) $entry->balance_after, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No transactions.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p style="margin-top:24px;font-size:10px;color:#666;">Generated {{ $generatedAt->format('Y-m-d H:i') }}</p>
</body>
</html>
