<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Receipt {{ $invoice->number ?? '' }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm 2mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans Mono', 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #000000;
            margin: 0;
            padding: 0;
            width: 72mm;
            max-width: 72mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ---- Store header ---- */
        .store-name {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 4px 0 2px 0;
        }

        .store-detail {
            font-size: 9px;
            text-align: center;
            color: #444444;
            margin: 1px 0;
        }

        /* ---- Separator ---- */
        .separator {
            border: none;
            border-top: 1px dashed #000000;
            margin: 6px 0;
        }

        /* ---- Receipt meta ---- */
        .meta-line {
            font-size: 10px;
            margin: 2px 0;
        }

        .meta-label {
            font-weight: bold;
        }

        /* ---- Items ---- */
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table td {
            padding: 1px 0;
            vertical-align: top;
            font-size: 10px;
        }

        .item-name-cell {
            width: 60%;
        }

        .item-total-cell {
            width: 40%;
            text-align: right;
            font-weight: bold;
        }

        .item-detail {
            font-size: 9px;
            color: #555555;
        }

        .item-detail-right {
            font-size: 9px;
            color: #555555;
            text-align: right;
        }

        .strikethrough {
            text-decoration: line-through;
            color: #999999;
        }

        /* ---- Totals ---- */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .totals-table td {
            padding: 2px 0;
            font-size: 10px;
        }

        .total-label-cell {
            width: 55%;
        }

        .total-amount-cell {
            width: 45%;
            text-align: right;
        }

        .total-row-grand td {
            font-size: 12px;
            font-weight: bold;
            padding: 3px 0;
        }

        /* ---- Payment rows ---- */
        .payment-method-row td {
            font-size: 10px;
            padding: 1px 0;
        }

        .change-row td {
            font-size: 10px;
            padding: 1px 0;
            font-style: italic;
        }

        /* ---- FBR ---- */
        .fbr-block {
            text-align: center;
            margin: 4px 0;
        }

        .fbr-number {
            font-size: 10px;
            font-weight: bold;
            word-break: break-all;
        }

        /* ---- Footer ---- */
        .thank-you {
            text-align: center;
            font-size: 10px;
            margin: 6px 0 2px 0;
        }

        .powered-by {
            text-align: center;
            font-size: 8px;
            color: #aaaaaa;
            margin: 2px 0 4px 0;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

@php
    $company ??= [
        'name'    => (string) \App\Models\SystemSetting::get('company', 'legal_name', ''),
        'address' => (string) \App\Models\SystemSetting::get('company', 'address', ''),
        'phone'   => (string) \App\Models\SystemSetting::get('company', 'phone', ''),
        'email'   => (string) \App\Models\SystemSetting::get('company', 'email', ''),
        'tax_id'  => (string) \App\Models\SystemSetting::get('company', 'tax_id', ''),
    ];
    $tax_enabled ??= (bool) \App\Models\SystemSetting::get('tax', 'enabled', true);
    $fbr_enabled ??= (bool) \App\Models\SystemSetting::get('fbr', 'enabled', false);
    $auto_print  ??= false;

    $storeName = $company['name'] ?: ($sale->branch->name ?? 'RETAILPULSE');

    $subtotal      = (float) ($sale->subtotal ?? 0);
    $totalDiscount = (float) ($sale->total_discount ?? 0);
    $taxTotal      = (float) ($sale->tax_total ?? 0);
    $grandTotal    = (float) ($sale->grand_total ?? 0);

    $paymentMethodLabels = [
        'cash'          => 'Cash',
        'card'          => 'Card',
        'mobile_wallet' => 'Mobile Wallet',
        'bank_transfer' => 'Bank Transfer',
        'credit'        => 'Credit',
    ];

    $changeDue = 0;
    foreach (($sale->payments ?? collect()) as $payment) {
        $meta = $payment->meta ?? [];
        if (isset($meta['change_due']) && (float) $meta['change_due'] > 0) {
            $changeDue += (float) $meta['change_due'];
        }
    }
@endphp

{{-- ======================== STORE HEADER ======================== --}}
<p class="store-name">{{ strtoupper($storeName) }}</p>

@if($company['address'])
    <p class="store-detail">{{ $company['address'] }}</p>
@endif

@if($company['phone'])
    <p class="store-detail">Tel: {{ $company['phone'] }}</p>
@endif

@if($company['tax_id'])
    <p class="store-detail">Tax ID: {{ $company['tax_id'] }}</p>
@endif

{{-- ======================== SEPARATOR ======================== --}}
<div class="separator"></div>

{{-- ======================== RECEIPT META ======================== --}}
<p class="meta-line">
    <span class="meta-label">Receipt #:</span> {{ $invoice->number ?? 'N/A' }}
</p>
<p class="meta-line">
    <span class="meta-label">Date:</span>
    {{ ($sale->completed_at ?? $sale->created_at ?? now())->format('d/m/Y h:i A') }}
</p>
<p class="meta-line">
    <span class="meta-label">Cashier:</span> {{ $sale->cashier->name ?? 'N/A' }}
</p>

@if($sale->customer)
    <p class="meta-line">
        <span class="meta-label">Customer:</span> {{ $sale->customer->name }}
        @if($sale->customer->phone)
            ({{ $sale->customer->phone }})
        @endif
    </p>
@endif

{{-- ======================== SEPARATOR ======================== --}}
<div class="separator"></div>

{{-- ======================== ITEMS ======================== --}}
@forelse($sale->items ?? [] as $item)
    @php
        $hasDiscount = (float)($item->discount_value ?? 0) > 0;
        $lineTotal   = (float)($tax_enabled ? $item->line_total_inc_tax : $item->line_total);

        if ($hasDiscount) {
            if ($item->discount_type === 'percent') {
                $discountedUnitPrice = (float)$item->unit_price * (1 - (float)$item->discount_value / 100);
            } else {
                $discountedUnitPrice = max(0, (float)$item->unit_price - (float)$item->discount_value);
            }
        }
    @endphp

    {{-- Item name + line total --}}
    <table class="items-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="item-name-cell">{{ $item->name }}</td>
            <td class="item-total-cell">{{ number_format($lineTotal, 2) }}</td>
        </tr>
        <tr>
            <td class="item-detail">
                @if($hasDiscount)
                    <span class="strikethrough">{{ number_format((float)$item->unit_price, 2) }}</span>
                    &rarr; {{ number_format($discountedUnitPrice, 2) }}
                    &times; {{ $item->quantity }}
                @else
                    {{ $item->quantity }} &times; {{ number_format((float)$item->unit_price, 2) }}
                @endif
            </td>
            <td class="item-detail-right">
                @if($tax_enabled && (float)($item->tax_rate ?? 0) > 0)
                    +{{ number_format((float)$item->tax_rate * 100, 0) }}% tax
                @endif
            </td>
        </tr>
    </table>
@empty
    <p style="text-align: center; color: #999999;">No items</p>
@endforelse

{{-- ======================== SEPARATOR ======================== --}}
<div class="separator"></div>

{{-- ======================== TOTALS ======================== --}}
<table class="totals-table" cellpadding="0" cellspacing="0">
    <tr>
        <td class="total-label-cell">Subtotal</td>
        <td class="total-amount-cell">{{ number_format($subtotal, 2) }}</td>
    </tr>
    @if($totalDiscount > 0)
        <tr>
            <td class="total-label-cell">Discount</td>
            <td class="total-amount-cell">- {{ number_format($totalDiscount, 2) }}</td>
        </tr>
    @endif
    @if($tax_enabled && $taxTotal > 0)
        <tr>
            <td class="total-label-cell">Tax</td>
            <td class="total-amount-cell">{{ number_format($taxTotal, 2) }}</td>
        </tr>
    @endif
</table>

<div class="separator"></div>

<table class="totals-table" cellpadding="0" cellspacing="0">
    <tr class="total-row-grand">
        <td class="total-label-cell">TOTAL</td>
        <td class="total-amount-cell">{{ number_format($grandTotal, 2) }}</td>
    </tr>
    @if((float)($sale->balance_due ?? 0) > 0)
        <tr>
            <td class="total-label-cell" style="font-style: italic;">Balance Due</td>
            <td class="total-amount-cell" style="font-style: italic;">{{ number_format((float)$sale->balance_due, 2) }}</td>
        </tr>
    @endif
</table>

{{-- ======================== PAYMENTS ======================== --}}
@if(($sale->payments ?? collect())->count() > 0)
    <div class="separator"></div>
    <table class="totals-table" cellpadding="0" cellspacing="0">
        @foreach($sale->payments as $payment)
            @if(($payment->status->value ?? $payment->status) === 'completed')
                <tr class="payment-method-row">
                    <td class="total-label-cell">
                        {{ $paymentMethodLabels[$payment->method->value ?? $payment->method] ?? ucfirst(str_replace('_', ' ', $payment->method->value ?? $payment->method)) }}
                    </td>
                    <td class="total-amount-cell">{{ number_format((float)$payment->amount, 2) }}</td>
                </tr>
            @endif
        @endforeach
        @if($changeDue > 0)
            <tr class="change-row">
                <td class="total-label-cell">Cash Change</td>
                <td class="total-amount-cell">{{ number_format($changeDue, 2) }}</td>
            </tr>
        @endif
    </table>
@endif

{{-- ======================== FBR ======================== --}}
@if($fbr_enabled && !empty($invoice->fbr_invoice_number))
    <div class="separator"></div>
    <div class="fbr-block">
        <p style="font-size: 9px; margin: 0 0 2px 0;">FBR Verified Invoice</p>
        <p class="fbr-number">FBR No: {{ $invoice->fbr_invoice_number }}</p>
        <p style="font-size: 8px; color: #555555; margin: 2px 0 0 0;">Verify at pos.fbr.gov.pk</p>
    </div>
@endif

{{-- ======================== FOOTER ======================== --}}
<div class="separator"></div>

<p class="thank-you">Thank you for your visit!</p>
<p class="powered-by">Powered by RetailPulse</p>

@if($auto_print)
    <script type="text/javascript">
        window.onload = function () {
            window.print();
            window.addEventListener('afterprint', function () {
                window.close();
            });
        };
    </script>
@endif

</body>
</html>
