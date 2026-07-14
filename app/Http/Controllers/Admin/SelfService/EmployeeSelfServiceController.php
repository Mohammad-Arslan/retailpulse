<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\SelfService;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Services\Payroll\EmployeeSelfServiceService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class EmployeeSelfServiceController extends Controller
{
    public function __construct(
        private readonly EmployeeSelfServiceService $selfService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewOwn', Payslip::class);

        $user = request()->user();
        $payslips = $this->selfService->listOwnPayslips($user);

        return Inertia::render('Admin/SelfService/Payslips/Index', [
            'payslips' => $payslips->map(fn (Payslip $payslip) => [
                'id' => $payslip->id,
                'payslip_number' => $payslip->payslip_number,
                'payroll_number' => $payslip->payrollItem?->payrollRun?->payroll_number,
                'period_start' => $payslip->payrollItem?->payrollRun?->period_start?->toDateString(),
                'period_end' => $payslip->payrollItem?->payrollRun?->period_end?->toDateString(),
                'currency_code' => $payslip->payrollItem?->payrollRun?->currency_code,
                'net_pay' => $payslip->payrollItem?->net_pay,
                'emailed_at' => $payslip->emailed_at?->toIso8601String(),
                'created_at' => $payslip->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function download(Payslip $payslip): BinaryFileResponse
    {
        $this->authorize('downloadOwn', $payslip);

        $payslip->loadMissing('payrollItem.payslip');

        if (! Storage::disk($payslip->disk)->exists($payslip->path)) {
            abort(404, 'Payslip File Not Found.');
        }

        return response()->file(
            Storage::disk($payslip->disk)->path($payslip->path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="payslip-'.$payslip->payslip_number.'.pdf"',
            ],
        );
    }
}
