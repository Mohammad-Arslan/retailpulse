<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Invoice {{ $invoice->number ?? '' }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #333333;
            margin: 0;
            padding: 0;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* ---- Layout tables ---- */
        .w-full { width: 100%; }
        .w-half { width: 50%; }

        /* ---- Header ---- */
        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #1a56db;
            margin: 0 0 4px 0;
        }

        .company-detail {
            font-size: 11px;
            color: #555555;
            margin: 1px 0;
        }

        .invoice-label {
            font-size: 28px;
            font-weight: bold;
            color: #222222;
            text-align: right;
            letter-spacing: 2px;
        }

        .invoice-meta {
            font-size: 11px;
            color: #555555;
            text-align: right;
            margin: 2px 0;
        }

        .invoice-meta strong {
            color: #222222;
        }

        /* ---- Divider ---- */
        .divider {
            border: none;
            border-top: 3px solid #1a56db;
            margin: 12px 0;
        }

        /* ---- Bill To / Served By ---- */
        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888888;
            margin-bottom: 4px;
        }

        .section-value {
            font-size: 12px;
            color: #222222;
            font-weight: bold;
        }

        .section-sub {
            font-size: 11px;
            color: #555555;
        }

        /* ---- Items table ---- */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .items-table thead tr {
            background-color: #1a56db;
            color: #ffffff;
        }

        .items-table thead th {
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .items-table thead th.text-right {
            text-align: right;
        }

        .items-table tbody tr.row-even {
            background-color: #f9f9f9;
        }

        .items-table tbody tr.row-odd {
            background-color: #ffffff;
        }

        .items-table tbody td {
            padding: 8px 10px;
            font-size: 11px;
            vertical-align: top;
            border-bottom: 1px solid #eeeeee;
        }

        .items-table tbody td.text-right {
            text-align: right;
        }

        .item-sku {
            font-size: 9px;
            color: #999999;
            margin-top: 2px;
        }

        /* ---- Totals + Payments ---- */
        .bottom-section {
            width: 100%;
            margin-top: 20px;
        }

        .bottom-left {
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }

        .bottom-right {
            width: 50%;
            vertical-align: top;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 5px 8px;
            font-size: 12px;
        }

        .totals-table td.label {
            text-align: left;
            color: #555555;
            width: 55%;
        }

        .totals-table td.amount {
            text-align: right;
            color: #222222;
            width: 45%;
        }

        .totals-table tr.discount td {
            color: #16a34a;
        }

        .totals-table tr.grand-total td {
            font-size: 14px;
            font-weight: bold;
            color: #1a56db;
            border-top: 2px solid #1a56db;
            border-bottom: 2px solid #1a56db;
            background-color: #f0f5ff;
            padding: 8px;
        }

        /* ---- Payment summary ---- */
        .payment-summary-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #555555;
            margin-bottom: 6px;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-table td {
            padding: 4px 6px;
            font-size: 11px;
        }

        .payment-table td.pay-label {
            color: #555555;
            width: 55%;
        }

        .payment-table td.pay-amount {
            text-align: right;
            color: #222222;
            font-weight: bold;
            width: 45%;
        }

        .payment-table tr.change-row td {
            color: #16a34a;
            font-style: italic;
        }

        /* ---- FBR box ---- */
        .fbr-box {
            border: 2px solid #d97706;
            background-color: #fffbeb;
            border-radius: 4px;
            padding: 8px 10px;
            margin-top: 12px;
        }

        .fbr-box-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #b45309;
            margin-bottom: 4px;
        }

        .fbr-box-number {
            font-size: 13px;
            font-weight: bold;
            color: #92400e;
            word-break: break-all;
        }

        /* ---- Footer ---- */
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999999;
            border-top: 1px solid #eeeeee;
            padding-top: 10px;
        }

        /* ---- Print overrides ---- */
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

{{-- ======================== HEADER ======================== --}}
<table class="w-full" cellpadding="0" cellspacing="0">
    <tr>
        {{-- Left: Company --}}
        <td class="w-half" style="vertical-align: top;">
            <p class="company-name">{{ $company['name'] ?: ($sale->branch->name ?? 'RetailPulse') }}</p>
            @if($company['address'])
                <p class="company-detail">{{ $company['address'] }}</p>
            @endif
            @if($company['phone'])
                <p class="company-detail">Tel: {{ $company['phone'] }}</p>
            @endif
            @if($company['email'])
                <p class="company-detail">{{ $company['email'] }}</p>
            @endif
            @if($company['tax_id'])
                <p class="company-detail">Tax ID: {{ $company['tax_id'] }}</p>
            @endif
        </td>

        {{-- Right: Invoice details --}}
        <td class="w-half" style="vertical-align: top; text-align: right;">
            <p class="invoice-label">INVOICE</p>
            <p class="invoice-meta"><strong>Invoice #:</strong> {{ $invoice->number ?? 'N/A' }}</p>
            <p class="invoice-meta">
                <strong>Date:</strong>
                {{ ($sale->completed_at ?? $sale->created_at ?? now())->format('d M Y, h:i A') }}
            </p>
            @if($sale->branch)
                <p class="invoice-meta"><strong>Branch:</strong> {{ $sale->branch->name }}</p>
            @endif
        </td>
    </tr>
</table>

<hr class="divider">

{{-- ======================== BILL TO / SERVED BY ======================== --}}
<table class="w-full" cellpadding="0" cellspacing="0">
    <tr>
        <td class="w-half" style="vertical-align: top; padding-right: 20px;">
            <p class="section-title">Bill To</p>
            @if($sale->customer)
                <p class="section-value">{{ $sale->customer->name }}</p>
                @if($sale->customer->phone)
                    <p class="section-sub">{{ $sale->customer->phone }}</p>
                @endif
                @if($sale->customer->email)
                    <p class="section-sub">{{ $sale->customer->email }}</p>
                @endif
                @if($sale->customer->ntn)
                    <p class="section-sub">NTN: {{ $sale->customer->ntn }}</p>
                @endif
            @else
                <p class="section-value">Walk-in Customer</p>
            @endif
        </td>
        <td class="w-half" style="vertical-align: top;">
            <p class="section-title">Served By</p>
            <p class="section-value">{{ $sale->cashier->name ?? 'N/A' }}</p>
            @if($sale->notes)
                <p class="section-sub" style="margin-top: 8px; font-style: italic;">Note: {{ $sale->notes }}</p>
            @endif
        </td>
    </tr>
</table>

{{-- ======================== ITEMS TABLE ======================== --}}
<table class="items-table" cellpadding="0" cellspacing="0">
    <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th style="width: {{ $tax_enabled ? '35%' : '45%' }};">Item</th>
            <th style="width: 8%; text-align: right;">Qty</th>
            <th style="width: 14%; text-align: right;">Unit Price</th>
            @if($tax_enabled)
                <th style="width: 8%; text-align: right;">Tax %</th>
                <th style="width: 13%; text-align: right;">Tax Amt</th>
            @endif
            <th style="width: 14%; text-align: right;">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($sale->items ?? [] as $index => $item)
            <tr class="{{ $index % 2 === 0 ? 'row-even' : 'row-odd' }}">
                <td>{{ $index + 1 }}</td>
                <td>
                    <span>{{ $item->name }}</span>
                    @if($item->sku)
                        <div class="item-sku">SKU: {{ $item->sku }}</div>
                    @endif
                </td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">
                    @if((float)($item->discount_value ?? 0) > 0)
                        <span style="text-decoration: line-through; color: #999999;">
                            {{ number_format((float)$item->unit_price, 2) }}
                        </span><br>
                        @php
                            if ($item->discount_type === 'percent') {
                                $discountedPrice = (float)$item->unit_price * (1 - (float)$item->discount_value / 100);
                            } else {
                                $discountedPrice = (float)$item->unit_price - (float)$item->discount_value;
                            }
                        @endphp
                        <span style="color: #16a34a;">{{ number_format($discountedPrice, 2) }}</span>
                    @else
                        {{ number_format((float)$item->unit_price, 2) }}
                    @endif
                </td>
                @if($tax_enabled)
                    <td class="text-right">
                        {{ (float)($item->tax_rate ?? 0) > 0 ? number_format((float)$item->tax_rate * 100, 0) . '%' : '—' }}
                    </td>
                    <td class="text-right">{{ number_format((float)($item->tax_amount ?? 0), 2) }}</td>
                @endif
                <td class="text-right" style="font-weight: bold;">
                    {{ number_format((float)($tax_enabled ? $item->line_total_inc_tax : $item->line_total), 2) }}
                </td>
            </tr>
        @empty
            <tr class="row-even">
                <td colspan="{{ $tax_enabled ? 7 : 5 }}" style="text-align: center; color: #999999; padding: 20px;">
                    No items found.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- ======================== TOTALS + PAYMENT SUMMARY ======================== --}}
<table class="bottom-section" cellpadding="0" cellspacing="0">
    <tr>
        {{-- Left: Payment Summary + FBR --}}
        <td class="bottom-left">
            @if(($sale->payments ?? collect())->count() > 0)
                <p class="payment-summary-title">Payment Summary</p>
                <table class="payment-table" cellpadding="0" cellspacing="0">
                    @foreach($sale->payments as $payment)
                        @if(($payment->status->value ?? $payment->status) === 'completed')
                            <tr>
                                <td class="pay-label">
                                    {{ $paymentMethodLabels[$payment->method->value ?? $payment->method] ?? ucfirst(str_replace('_', ' ', $payment->method->value ?? $payment->method)) }}
                                </td>
                                <td class="pay-amount">{{ number_format((float)$payment->amount, 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @if($changeDue > 0)
                        <tr class="change-row">
                            <td class="pay-label">Change Given</td>
                            <td class="pay-amount" style="color: #16a34a;">{{ number_format($changeDue, 2) }}</td>
                        </tr>
                    @endif
                </table>
            @endif

            @if($fbr_enabled && !empty($invoice->fbr_invoice_number))
                <div class="fbr-box">
                    <p class="fbr-box-title">FBR Verification Number</p>
                    <p class="fbr-box-number">{{ $invoice->fbr_invoice_number }}</p>
                    <p style="font-size: 9px; color: #b45309; margin: 4px 0 0 0;">
                        Verify at: pos.fbr.gov.pk
                    </p>
                </div>
            @endif
        </td>

        {{-- Right: Totals --}}
        <td class="bottom-right">
            <table class="totals-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="amount">{{ number_format($subtotal, 2) }}</td>
                </tr>
                @if($totalDiscount > 0)
                    <tr class="discount">
                        <td class="label">Discount</td>
                        <td class="amount">- {{ number_format($totalDiscount, 2) }}</td>
                    </tr>
                @endif
                @if($tax_enabled && $taxTotal > 0)
                    <tr>
                        <td class="label">Tax</td>
                        <td class="amount">{{ number_format($taxTotal, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td class="label">Grand Total</td>
                    <td class="amount">{{ number_format($grandTotal, 2) }}</td>
                </tr>
                @if((float)($sale->balance_due ?? 0) > 0)
                    <tr>
                        <td class="label" style="color: #dc2626;">Balance Due</td>
                        <td class="amount" style="color: #dc2626; font-weight: bold;">
                            {{ number_format((float)$sale->balance_due, 2) }}
                        </td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- ======================== FOOTER ======================== --}}
<div class="footer">
    <p>Thank you for shopping with us!</p>
    @if($sale->branch)
        <p>{{ $sale->branch->name }}@if($company['phone']) &bull; {{ $company['phone'] }}@endif</p>
    @endif
    <p style="margin-top: 4px; font-size: 9px; color: #bbbbbb;">Powered by RetailPulse</p>
</div>

@if($auto_print)
    <script type="text/javascript">
        window.onload = function () {
            window.print();
        };
    </script>
@endif

</body>
</html>
