<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Debit Note {{ $debitNote->reference_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Debit Note {{ $debitNote->reference_no }}</h1>
    <div class="meta">
        <strong>{{ $company['legal_name'] }}</strong><br>
        Supplier: {{ $debitNote->supplier?->name }}<br>
        Branch: {{ $debitNote->branch?->name }}<br>
        Return: {{ $debitNote->purchaseReturn?->reference_no ?? '—' }}<br>
        Issued: {{ $debitNote->issued_at?->format('Y-m-d') ?? '—' }}<br>
        Amount: {{ number_format((float) $debitNote->amount, 2) }} {{ $debitNote->currency_code }}
    </div>
    <p style="margin-top:24px;font-size:10px;color:#666;">Generated {{ $generatedAt->format('Y-m-d H:i') }}</p>
</body>
</html>
