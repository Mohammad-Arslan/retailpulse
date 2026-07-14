<x-mail::message>
# {{ __('Your Payslip Is Available') }}

{{ __('Hello :name,', ['name' => $employeeName]) }}

{{ __('Your payslip :number for the period :start to :end is attached.', [
    'number' => $payslipNumber,
    'start' => $periodStart ?? '—',
    'end' => $periodEnd ?? '—',
]) }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
