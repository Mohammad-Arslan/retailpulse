<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Mail\PayslipMail;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Accounting\DocumentNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Payslip PDF generation and delivery.
 * NO JournalService — storage and mail only.
 */
final class PayslipService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    public function generateForItem(PayrollItem $item): Payslip
    {
        $item->loadMissing([
            'lines',
            'employee.legalEntity',
            'employee.primaryBranch',
            'payrollRun.legalEntity',
            'payrollRun.branch',
            'payslip',
        ]);

        $totals = $this->resolveTotals($item);
        $this->assertTotalsMatchItem($item, $totals);

        $run = $item->payrollRun;
        if ($run === null) {
            throw new DomainException('Payroll Item Has No Associated Run.');
        }

        $disk = (string) config('payroll.payslips_disk', 'local');
        $payslipNumber = $item->payslip?->payslip_number ?? $this->documentNumbers->next(
            'payslip',
            'PS',
            $run->branch_id,
        );

        $pdf = Pdf::loadView('payslips.standard', [
            'item' => $item,
            'run' => $run,
            'employee' => $item->employee,
            'totals' => $totals,
            'payslipNumber' => $payslipNumber,
        ]);

        $path = sprintf(
            'payslips/%d/%d/%s.pdf',
            $run->id,
            $item->employee_id,
            $payslipNumber,
        );

        Storage::disk($disk)->put($path, $pdf->output());

        return Payslip::query()->updateOrCreate(
            ['payroll_item_id' => $item->id],
            [
                'payslip_number' => $payslipNumber,
                'disk' => $disk,
                'path' => $path,
            ],
        );
    }

    /**
     * @return array{
     *     gross: string,
     *     total_deductions: string,
     *     total_employer_contributions: string,
     *     net_pay: string,
     *     earnings: list<array{code: string, name: string, amount: string}>,
     *     deductions: list<array{code: string, name: string, amount: string}>,
     *     employer_contributions: list<array{code: string, name: string, amount: string}>
     * }
     */
    public function resolveTotals(PayrollItem $item): array
    {
        $item->loadMissing('lines');

        $earnings = [];
        $deductions = [];
        $employerContributions = [];

        $gross = '0.0000';
        $totalDeductions = '0.0000';
        $totalEmployer = '0.0000';

        foreach ($item->lines as $line) {
            $snapshot = $line->component_snapshot_json ?? [];
            $type = $snapshot['type'] ?? null;
            $amount = (string) $line->amount;
            $row = [
                'code' => (string) ($snapshot['code'] ?? ''),
                'name' => (string) ($snapshot['name'] ?? ''),
                'amount' => $amount,
            ];

            if (in_array($type, ['earning', 'reimbursement'], true)) {
                $earnings[] = $row;
                $gross = bcadd($gross, $amount, 4);
            } elseif (in_array($type, ['deduction', 'statutory'], true)) {
                $deductions[] = $row;
                $totalDeductions = bcadd($totalDeductions, $amount, 4);
            } elseif ($type === 'employer_contribution') {
                $employerContributions[] = $row;
                $totalEmployer = bcadd($totalEmployer, $amount, 4);
            }
        }

        $netPay = bcsub($gross, $totalDeductions, 4);

        return [
            'gross' => $gross,
            'total_deductions' => $totalDeductions,
            'total_employer_contributions' => $totalEmployer,
            'net_pay' => $netPay,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'employer_contributions' => $employerContributions,
        ];
    }

    public function emailItem(PayrollItem $item): void
    {
        $payslip = $item->payslip ?? $this->generateForItem($item);
        $item->loadMissing('employee');

        $recipient = $this->resolveRecipientEmail($item);

        if ($recipient === null) {
            throw new DomainException('Employee Has No Email Address For Payslip Delivery.');
        }

        Mail::queue(new PayslipMail($payslip, $recipient));

        $payslip->update(['emailed_at' => now()]);
    }

    /**
     * @return array{sent: int, skipped: int}
     */
    public function bulkEmail(PayrollRun $run): array
    {
        $run->loadMissing(['items.employee', 'items.payslip']);

        $sent = 0;
        $skipped = 0;

        foreach ($run->items as $item) {
            if ($this->resolveRecipientEmail($item) === null) {
                $skipped++;

                continue;
            }

            $this->emailItem($item);
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * @param  array{
     *     gross: string,
     *     total_deductions: string,
     *     total_employer_contributions: string,
     *     net_pay: string
     * }  $totals
     */
    private function assertTotalsMatchItem(PayrollItem $item, array $totals): void
    {
        if (bccomp($totals['gross'], (string) $item->gross, 4) !== 0) {
            throw new DomainException('Payslip Gross Does Not Match Payroll Item.');
        }

        if (bccomp($totals['total_deductions'], (string) $item->total_deductions, 4) !== 0) {
            throw new DomainException('Payslip Deductions Do Not Match Payroll Item.');
        }

        if (bccomp($totals['total_employer_contributions'], (string) $item->total_employer_contributions, 4) !== 0) {
            throw new DomainException('Payslip Employer Contributions Do Not Match Payroll Item.');
        }

        if (bccomp($totals['net_pay'], (string) $item->net_pay, 4) !== 0) {
            throw new DomainException('Payslip Net Pay Does Not Match Payroll Item.');
        }
    }

    private function resolveRecipientEmail(PayrollItem $item): ?string
    {
        $employee = $item->employee;

        if ($employee === null) {
            return null;
        }

        $email = $employee->email;

        if ($email !== null && $email !== '') {
            return $email;
        }

        $employee->loadMissing('user');

        return $employee->user?->email;
    }
}
