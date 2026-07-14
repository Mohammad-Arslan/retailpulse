<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Payroll\PayslipService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PayslipController extends Controller
{
    public function __construct(
        private readonly PayslipService $payslips,
    ) {}

    public function generate(PayrollItem $payrollItem): BinaryFileResponse
    {
        $this->authorize('generate', Payslip::class);

        try {
            $payslip = $this->payslips->generateForItem($payrollItem);
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        return response()->file(
            Storage::disk($payslip->disk)->path($payslip->path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="payslip-'.$payslip->payslip_number.'.pdf"',
            ],
        );
    }

    public function download(PayrollItem $payrollItem): BinaryFileResponse
    {
        $this->authorize('generate', Payslip::class);

        $payrollItem->loadMissing('payslip');
        $payslip = $payrollItem->payslip;

        if ($payslip === null || ! Storage::disk($payslip->disk)->exists($payslip->path)) {
            $payslip = $this->payslips->generateForItem($payrollItem);
        }

        return response()->file(
            Storage::disk($payslip->disk)->path($payslip->path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="payslip-'.$payslip->payslip_number.'.pdf"',
            ],
        );
    }

    public function bulkEmail(PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('bulkEmail', Payslip::class);
        $this->authorize('process', $payrollRun);

        try {
            $result = $this->payslips->bulkEmail($payrollRun);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':sent Payslip(s) Queued For Email. :skipped Skipped (No Email).', [
            'sent' => $result['sent'],
            'skipped' => $result['skipped'],
        ]));
    }
}
