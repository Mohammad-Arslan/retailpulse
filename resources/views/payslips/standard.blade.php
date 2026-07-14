<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Payslip {{ $payslipNumber }}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #333333;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }
        .header-table { width: 100%; margin-bottom: 16px; }
        .title { font-size: 24px; font-weight: bold; color: #1a56db; }
        .meta { font-size: 11px; color: #555555; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #666666;
            margin: 16px 0 6px;
            border-bottom: 1px solid #dddddd;
            padding-bottom: 4px;
        }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.lines th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #666666;
            border-bottom: 1px solid #cccccc;
            padding: 6px 4px;
        }
        table.lines td { padding: 6px 4px; border-bottom: 1px solid #eeeeee; }
        table.lines td.amount { text-align: right; white-space: nowrap; }
        .totals-table { width: 100%; margin-top: 8px; }
        .totals-table td { padding: 4px 0; }
        .totals-table td.label { text-align: right; padding-right: 12px; color: #555555; }
        .totals-table td.value { text-align: right; font-weight: bold; width: 120px; }
        .net-row td { font-size: 14px; font-weight: bold; color: #1a56db; border-top: 2px solid #1a56db; padding-top: 8px; }
        .ytd { margin-top: 20px; font-size: 11px; color: #555555; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="title">Payslip</div>
                <div class="meta">{{ $run->legalEntity?->legal_name ?? '' }}</div>
                @if($run->branch)
                    <div class="meta">{{ $run->branch->name }}</div>
                @endif
            </td>
            <td style="text-align: right;">
                <div class="meta"><strong>Payslip #:</strong> {{ $payslipNumber }}</div>
                <div class="meta"><strong>Payroll #:</strong> {{ $run->payroll_number ?? '—' }}</div>
                <div class="meta"><strong>Period:</strong> {{ $run->period_start?->format('d M Y') }} – {{ $run->period_end?->format('d M Y') }}</div>
                <div class="meta"><strong>Currency:</strong> {{ $run->currency_code }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Employee</div>
    <div class="meta"><strong>{{ $employee?->fullName() ?? '' }}</strong> ({{ $employee?->employee_code ?? '' }})</div>

    <div class="section-title">Earnings</div>
    <table class="lines">
        <thead>
            <tr>
                <th>Code</th>
                <th>Description</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($totals['earnings'] as $line)
                <tr>
                    <td>{{ $line['code'] }}</td>
                    <td>{{ $line['name'] }}</td>
                    <td class="amount">{{ number_format((float) $line['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">—</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(count($totals['deductions']) > 0)
        <div class="section-title">Deductions</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($totals['deductions'] as $line)
                    <tr>
                        <td>{{ $line['code'] }}</td>
                        <td>{{ $line['name'] }}</td>
                        <td class="amount">{{ number_format((float) $line['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(count($totals['employer_contributions']) > 0)
        <div class="section-title">Employer Contributions (Informational)</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($totals['employer_contributions'] as $line)
                    <tr>
                        <td>{{ $line['code'] }}</td>
                        <td>{{ $line['name'] }}</td>
                        <td class="amount">{{ number_format((float) $line['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totals-table">
        <tr>
            <td class="label">Gross Pay</td>
            <td class="value">{{ number_format((float) $totals['gross'], 2) }}</td>
        </tr>
        <tr>
            <td class="label">Total Deductions</td>
            <td class="value">{{ number_format((float) $totals['total_deductions'], 2) }}</td>
        </tr>
        <tr class="net-row">
            <td class="label">Net Pay</td>
            <td class="value">{{ number_format((float) $totals['net_pay'], 2) }}</td>
        </tr>
    </table>

    @if(is_array($item->ytd_json) && count($item->ytd_json) > 0)
        <div class="ytd">
            <strong>Year-To-Date</strong>
            @foreach($item->ytd_json as $key => $value)
                <div>{{ ucwords(str_replace('_', ' ', $key)) }}: {{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</div>
            @endforeach
        </div>
    @endif
</body>
</html>
