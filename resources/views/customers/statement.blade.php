<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Customer Statement') }} — {{ $customer->name }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .amount { text-align: right; }
        .summary { margin-top: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $company['legal_name'] ?: config('app.name') }}</h1>
    @if ($company['address'])
        <div class="meta">{{ $company['address'] }}</div>
    @endif

    <h2>{{ __('Customer Statement') }}</h2>
    <div class="meta">
        <strong>{{ $customer->name }}</strong><br>
        @if ($customer->phone) {{ $customer->phone }}<br> @endif
        @if ($customer->email) {{ $customer->email }}<br> @endif
        {{ __('Generated') }}: {{ $generatedAt->format('Y-m-d H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Branch') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Reference') }}</th>
                <th class="amount">{{ __('Amount') }}</th>
                <th class="amount">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->created_at?->format('Y-m-d') }}</td>
                    <td>{{ $entry->branch?->name }}</td>
                    <td>{{ $entry->entry_type->value }}</td>
                    <td>{{ $entry->reference ?? ($entry->sale?->invoice?->number ?? '—') }}</td>
                    <td class="amount">{{ number_format((float) $entry->amount, 2) }}</td>
                    <td class="amount">{{ number_format((float) $entry->balance_after, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">{{ __('No ledger entries found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        {{ __('Outstanding balance') }}: {{ number_format($outstanding, 2) }}
    </div>
</body>
</html>
