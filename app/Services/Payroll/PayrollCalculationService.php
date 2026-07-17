<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\LeaveEncashment;
use App\Models\LeaveRequest;
use App\Models\PayComponent;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\ToilClaim;
use App\Services\Leave\LeaveService;
use App\Services\Overtime\OvertimeEngine;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Config-driven payroll calculation engine.
 * Reads salary structures, tax slabs, and statutory schemes from DB only — no hardcoded rates.
 *
 * NO JournalService imports. NO accounting event emit in this block.
 * Block 6 only creates/processes draft runs.
 */
final class PayrollCalculationService
{
    public function __construct(
        private readonly StatutoryResolverService $statutoryResolver,
        private readonly OvertimeEngine $overtimeEngine,
        private readonly LeaveService $leaveService,
    ) {}

    /**
     * Calculate items for a draft run. Status stays draft (no GL posting in Block 6).
     */
    public function processRun(PayrollRun $run): PayrollRun
    {
        if ($run->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => __('Only Draft Payroll Runs Can Be Calculated.'),
            ]);
        }

        $employees = Employee::query()
            ->where('legal_entity_id', $run->legal_entity_id)
            ->when($run->branch_id !== null, fn ($q) => $q->where('primary_branch_id', $run->branch_id))
            ->where('status', 'active')
            ->whereNotNull('salary_structure_id')
            ->with(['salaryStructure.components.component.basisComponent'])
            ->get();

        return DB::transaction(function () use ($run, $employees): PayrollRun {
            $run->items()->delete();

            $totalGross = '0.0000';
            $totalDeductions = '0.0000';
            $totalEmployerContributions = '0.0000';
            $totalNet = '0.0000';

            foreach ($employees as $employee) {
                $item = $this->processItem($run, $employee);
                $totalGross = bcadd($totalGross, (string) $item->gross, 4);
                $totalDeductions = bcadd($totalDeductions, (string) $item->total_deductions, 4);
                $totalEmployerContributions = bcadd($totalEmployerContributions, (string) $item->total_employer_contributions, 4);
                $totalNet = bcadd($totalNet, (string) $item->net_pay, 4);
            }

            $run->update([
                'status' => 'draft',
                'totals_json' => [
                    'employee_count' => $employees->count(),
                    'total_gross' => $totalGross,
                    'total_deductions' => $totalDeductions,
                    'total_employer_contributions' => $totalEmployerContributions,
                    'total_net' => $totalNet,
                    'calculated_at' => now()->toIso8601String(),
                ],
            ]);

            return $run->fresh(['items.lines']) ?? $run;
        });
    }

    /**
     * Calculate payroll for a single employee on the given run.
     * Freezes a full snapshot_json on the item for audit fidelity.
     */
    public function processItem(PayrollRun $run, Employee $employee): PayrollItem
    {
        $structure = $employee->salaryStructure;

        if ($structure === null) {
            throw new DomainException(__('Employee :code has no salary structure assigned.', [
                'code' => $employee->employee_code,
            ]));
        }

        $structure->loadMissing(['components.component.basisComponent']);

        $periodStart = CarbonImmutable::parse($run->period_start);
        $periodEnd = CarbonImmutable::parse($run->period_end);
        $periodMonths = max(1, (int) round($periodStart->diffInMonths($periodEnd) + 1));

        $componentAmounts = [];  // code → bcmath string (earnings/deductions)
        $lineData = [];
        $sequence = 10;

        // Step 1 — Salary structure components (fixed, percentage_of, table_lookup)
        foreach ($structure->components->sortBy('sequence') as $sc) {
            /** @var SalaryStructureComponent $sc */
            $component = $sc->component;
            if ($component === null || $component->status !== 'active') {
                continue;
            }

            $amount = $this->resolveComponentAmount(
                $component,
                $sc,
                $componentAmounts,
                $run->legal_entity_id,
                $periodStart,
                $periodMonths,
            );

            $componentAmounts[$component->code] = $amount;

            $lineData[] = [
                'pay_component_id' => $component->id,
                'component_snapshot_json' => [
                    'code' => $component->code,
                    'name' => $component->name,
                    'type' => $component->type,
                    'calculation_type' => $component->calculation_type,
                    'amount_or_rate' => (string) ($sc->amount_or_rate ?? '0'),
                    'rate' => (string) ($component->rate ?? '0'),
                    'taxable' => $component->taxable,
                    'account_mapping_key' => $component->account_mapping_key,
                ],
                'amount' => $amount,
                'sequence' => $sequence,
            ];
            $sequence += 10;
        }

        // Step 2 — Approved overtime → OVERTIME_EXPENSE component if present
        $overtimeLines = $this->buildOvertimeLines($employee, $periodStart, $periodEnd, $structure, $sequence);
        foreach ($overtimeLines as $line) {
            $code = $line['component_snapshot_json']['code'];
            $componentAmounts[$code] = bcadd($componentAmounts[$code] ?? '0.0000', $line['amount'], 4);
            $lineData[] = $line;
            $sequence += 10;
        }

        // Step 3 — Unpaid leave deductions via LeaveService
        $leaveLines = $this->buildLeaveDeductionLines($employee, $periodStart, $periodEnd, $componentAmounts, $sequence);
        foreach ($leaveLines as $line) {
            $lineData[] = $line;
            $sequence += 10;
        }

        // Step 3b — Approved leave encashments → earning component via LeaveType.payroll_encashment_component_code
        $encashmentLines = $this->buildLeaveEncashmentLines($employee, $periodStart, $periodEnd, $componentAmounts, $sequence);
        foreach ($encashmentLines as $line) {
            $code = $line['component_snapshot_json']['code'];
            $componentAmounts[$code] = bcadd($componentAmounts[$code] ?? '0.0000', $line['amount'], 4);
            $lineData[] = $line;
            $sequence += 10;
        }

        // Step 3c — Approved TOIL cash claims → earning component via LeaveType.payroll_toil_payout_component_code
        $toilCashLines = $this->buildToilCashClaimLines($employee, $periodStart, $periodEnd, $componentAmounts, $sequence);
        foreach ($toilCashLines as $line) {
            $code = $line['component_snapshot_json']['code'];
            $componentAmounts[$code] = bcadd($componentAmounts[$code] ?? '0.0000', $line['amount'], 4);
            $lineData[] = $line;
            $sequence += 10;
        }

        // Step 4 — Statutory schemes (employee + employer, generic handler)
        $grossEarnings = $this->sumLinesByType($lineData, ['earning', 'reimbursement'], $componentAmounts);
        $statutoryLines = $this->buildStatutoryLines($run->legal_entity_id, $periodStart, $grossEarnings, $sequence);
        foreach ($statutoryLines as $line) {
            $lineData[] = $line;
            $sequence += 10;
        }

        // Step 5 — Aggregate totals
        $totalGross = $this->sumLinesByTypes($lineData, ['earning', 'reimbursement']);
        $totalDeductions = $this->sumLinesByTypes($lineData, ['deduction', 'statutory']);
        $totalEmployerContributions = $this->sumLinesByTypes($lineData, ['employer_contribution']);
        $netPay = bcsub($totalGross, $totalDeductions, 4);

        // Step 6 — Persist item with frozen snapshot
        /** @var PayrollItem $item */
        $item = PayrollItem::query()->updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                'gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_employer_contributions' => $totalEmployerContributions,
                'net_pay' => $netPay,
                'ytd_json' => $this->buildYtd($employee, $run),
                'snapshot_json' => [
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->fullName(),
                    'salary_structure_code' => $structure->code,
                    'salary_structure_name' => $structure->name,
                    'period_start' => $run->period_start,
                    'period_end' => $run->period_end,
                    'period_months' => $periodMonths,
                    'currency_code' => $run->currency_code,
                    'calculated_at' => now()->toIso8601String(),
                    'component_amounts' => $componentAmounts,
                ],
            ],
        );

        // Step 7 — Persist lines (replace on reprocess)
        PayrollItemLine::query()->where('payroll_item_id', $item->id)->delete();

        foreach ($lineData as $line) {
            PayrollItemLine::query()->create(array_merge($line, ['payroll_item_id' => $item->id]));
        }

        return $item->fresh(['lines']) ?? $item;
    }

    // -------------------------------------------------------------------------
    // Private calculation helpers
    // -------------------------------------------------------------------------

    private function resolveComponentAmount(
        PayComponent $component,
        SalaryStructureComponent $sc,
        array $componentAmounts,
        ?int $legalEntityId,
        CarbonImmutable $periodStart,
        int $periodMonths,
    ): string {
        return match ($component->calculation_type) {
            'fixed' => bcadd((string) ($sc->amount_or_rate ?? '0'), '0', 4),
            'percentage_of' => $this->calcPercentageOf($component, $sc, $componentAmounts),
            'table_lookup' => $this->calcTableLookup($component, $componentAmounts, $legalEntityId, $periodStart, $periodMonths),
            'formula' => throw new DomainException(__('Formula components are not supported in payroll calculation.')),
            default => '0.0000',
        };
    }

    private function calcPercentageOf(
        PayComponent $component,
        SalaryStructureComponent $sc,
        array $componentAmounts,
    ): string {
        $basisCode = $component->basisComponent?->code;

        if ($basisCode === null || ! isset($componentAmounts[$basisCode])) {
            return '0.0000';
        }

        $basisAmount = $componentAmounts[$basisCode];
        $rateDecimal = bcdiv((string) ($component->rate ?? $sc->amount_or_rate ?? '0'), '100', 8);

        return bcmul($basisAmount, $rateDecimal, 4);
    }

    private function calcTableLookup(
        PayComponent $component,
        array $componentAmounts,
        ?int $legalEntityId,
        CarbonImmutable $periodStart,
        int $periodMonths,
    ): string {
        // Sum all taxable earning amounts calculated so far
        $taxableGross = '0.0000';
        foreach ($componentAmounts as $code => $amount) {
            // We do not filter by component type here — every amount in the map is an earning (deductions aren't added yet)
            $taxableGross = bcadd($taxableGross, $amount, 4);
        }

        return $this->statutoryResolver->resolveTaxFromSlabs(
            $taxableGross,
            $periodMonths,
            $legalEntityId,
            $periodStart,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildOvertimeLines(
        Employee $employee,
        CarbonImmutable $start,
        CarbonImmutable $end,
        SalaryStructure $structure,
        int $startSequence,
    ): array {
        $records = $this->overtimeEngine->approvedRecordsForPeriod($employee, $start, $end);

        if ($records->isEmpty()) {
            return [];
        }

        $overtimeCode = (string) config('payroll.overtime_component_code', 'OVERTIME_EXPENSE');
        $overtimeSc = $structure->components->first(
            fn (SalaryStructureComponent $sc) => $sc->component?->code === $overtimeCode,
        );

        if ($overtimeSc === null || $overtimeSc->component === null) {
            return [];
        }

        $overtimeComponent = $overtimeSc->component;

        $totalPayUnits = $records->reduce(
            fn (string $carry, $record): string => bcadd($carry, $this->overtimeEngine->calculatePayUnits($record), 4),
            '0.0000',
        );

        // hourly_rate stored as amount_or_rate on the structure component
        $hourlyRate = (string) ($overtimeSc->amount_or_rate ?? '0');
        // amount = pay_units / 60 * hourly_rate (pay_units are minutes * multiplier)
        $amount = bcmul(bcdiv($totalPayUnits, '60', 8), $hourlyRate, 4);

        return [[
            'pay_component_id' => $overtimeComponent->id,
            'component_snapshot_json' => [
                'code' => $overtimeComponent->code,
                'name' => $overtimeComponent->name,
                'type' => $overtimeComponent->type,
                'calculation_type' => $overtimeComponent->calculation_type,
                'hourly_rate' => $hourlyRate,
                'total_pay_units' => $totalPayUnits,
                'account_mapping_key' => $overtimeComponent->account_mapping_key,
            ],
            'amount' => $amount,
            'sequence' => $startSequence,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLeaveDeductionLines(
        Employee $employee,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $componentAmounts,
        int $startSequence,
    ): array {
        $leaveRequests = LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get();

        if ($leaveRequests->isEmpty()) {
            return [];
        }

        // Group leave days by component code
        $daysByCode = [];

        foreach ($leaveRequests as $request) {
            try {
                $componentCode = $this->leaveService->resolvePayrollDeductionComponent($employee, $request);
            } catch (DomainException) {
                continue;
            }

            if ($componentCode === null) {
                continue;
            }

            $daysByCode[$componentCode] = bcadd(
                $daysByCode[$componentCode] ?? '0.0000',
                (string) $request->days,
                4,
            );
        }

        if (empty($daysByCode)) {
            return [];
        }

        $lines = [];
        $sequence = $startSequence;

        foreach ($daysByCode as $componentCode => $totalDays) {
            $component = PayComponent::query()
                ->with('basisComponent')
                ->where('code', $componentCode)
                ->where('status', 'active')
                ->first();

            if ($component === null) {
                continue;
            }

            $daysInMonth = (string) max(1, (int) config('payroll.leave_days_in_month', 30));
            $basisCode = $component->basisComponent?->code ?? (string) config('payroll.default_basis_component_code', 'BASIC');
            $basisAmount = $componentAmounts[$basisCode] ?? '0.0000';
            $dailyRate = bcdiv($basisAmount, $daysInMonth, 8);
            $amount = bcmul($dailyRate, $totalDays, 4);

            $lines[] = [
                'pay_component_id' => $component->id,
                'component_snapshot_json' => [
                    'code' => $component->code,
                    'name' => $component->name,
                    'type' => $component->type,
                    'calculation_type' => $component->calculation_type,
                    'leave_days' => $totalDays,
                    'basis_code' => $basisCode,
                    'daily_rate' => $dailyRate,
                    'account_mapping_key' => $component->account_mapping_key,
                ],
                'amount' => $amount,
                'sequence' => $sequence,
            ];
            $sequence += 10;
        }

        return $lines;
    }

    /**
     * Approved leave encashments (scoped to this run's period via `approved_at`, mirroring
     * how leave deduction lines are scoped by leave_requests.start_date) posted as an earning
     * against LeaveType.payroll_encashment_component_code.
     *
     * @param  array<string, string>  $componentAmounts
     * @return list<array<string, mixed>>
     */
    private function buildLeaveEncashmentLines(
        Employee $employee,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $componentAmounts,
        int $startSequence,
    ): array {
        $encashments = LeaveEncashment::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('approved_at', [$start->startOfDay(), $end->endOfDay()])
            ->get();

        if ($encashments->isEmpty()) {
            return [];
        }

        $daysByCode = [];

        foreach ($encashments as $encashment) {
            $componentCode = $encashment->payroll_component_code;

            if ($componentCode === null) {
                continue;
            }

            $daysByCode[$componentCode] = bcadd(
                $daysByCode[$componentCode] ?? '0.0000',
                (string) $encashment->days,
                4,
            );
        }

        if (empty($daysByCode)) {
            return [];
        }

        $lines = [];
        $sequence = $startSequence;

        foreach ($daysByCode as $componentCode => $totalDays) {
            $component = PayComponent::query()
                ->with('basisComponent')
                ->where('code', $componentCode)
                ->where('status', 'active')
                ->first();

            if ($component === null) {
                continue;
            }

            $daysInMonth = (string) max(1, (int) config('payroll.leave_days_in_month', 30));
            $basisCode = $component->basisComponent?->code ?? (string) config('payroll.default_basis_component_code', 'BASIC');
            $basisAmount = $componentAmounts[$basisCode] ?? '0.0000';
            $dailyRate = bcdiv($basisAmount, $daysInMonth, 8);
            $amount = bcmul($dailyRate, $totalDays, 4);

            $lines[] = [
                'pay_component_id' => $component->id,
                'component_snapshot_json' => [
                    'code' => $component->code,
                    'name' => $component->name,
                    'type' => $component->type,
                    'calculation_type' => $component->calculation_type,
                    'encashed_days' => $totalDays,
                    'basis_code' => $basisCode,
                    'daily_rate' => $dailyRate,
                    'account_mapping_key' => $component->account_mapping_key,
                ],
                'amount' => $amount,
                'sequence' => $sequence,
            ];
            $sequence += 10;
        }

        return $lines;
    }

    /**
     * Approved TOIL cash claims (scoped to this run's period via `approved_at`,
     * same convention as leave encashment lines) posted as an earning against
     * LeaveType.payroll_toil_payout_component_code. Hours are converted to an
     * amount via the same daily-rate formula as leave encashment, further
     * divided by the employee's configured work_hours_per_day (Leave module
     * setting) to get an hourly rate — no second rate table to maintain.
     *
     * @param  array<string, string>  $componentAmounts
     * @return list<array<string, mixed>>
     */
    private function buildToilCashClaimLines(
        Employee $employee,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $componentAmounts,
        int $startSequence,
    ): array {
        $claims = ToilClaim::query()
            ->where('employee_id', $employee->id)
            ->where('claim_type', 'cash')
            ->where('status', 'approved')
            ->whereBetween('approved_at', [$start->startOfDay(), $end->endOfDay()])
            ->get();

        if ($claims->isEmpty()) {
            return [];
        }

        $hoursByCode = [];

        foreach ($claims as $claim) {
            $componentCode = $claim->payroll_component_code;

            if ($componentCode === null) {
                continue;
            }

            $hoursByCode[$componentCode] = bcadd(
                $hoursByCode[$componentCode] ?? '0.0000',
                (string) $claim->hours,
                4,
            );
        }

        if (empty($hoursByCode)) {
            return [];
        }

        $lines = [];
        $sequence = $startSequence;
        $workHoursPerDay = max(0.01, $this->leaveService->resolveWorkHoursPerDay($employee));

        foreach ($hoursByCode as $componentCode => $totalHours) {
            $component = PayComponent::query()
                ->with('basisComponent')
                ->where('code', $componentCode)
                ->where('status', 'active')
                ->first();

            if ($component === null) {
                continue;
            }

            $daysInMonth = (string) max(1, (int) config('payroll.leave_days_in_month', 30));
            $basisCode = $component->basisComponent?->code ?? (string) config('payroll.default_basis_component_code', 'BASIC');
            $basisAmount = $componentAmounts[$basisCode] ?? '0.0000';
            $dailyRate = bcdiv($basisAmount, $daysInMonth, 8);
            $hourlyRate = bcdiv($dailyRate, (string) $workHoursPerDay, 8);
            $amount = bcmul($hourlyRate, $totalHours, 4);

            $lines[] = [
                'pay_component_id' => $component->id,
                'component_snapshot_json' => [
                    'code' => $component->code,
                    'name' => $component->name,
                    'type' => $component->type,
                    'calculation_type' => $component->calculation_type,
                    'toil_hours' => $totalHours,
                    'basis_code' => $basisCode,
                    'daily_rate' => $dailyRate,
                    'hourly_rate' => $hourlyRate,
                    'account_mapping_key' => $component->account_mapping_key,
                ],
                'amount' => $amount,
                'sequence' => $sequence,
            ];
            $sequence += 10;
        }

        return $lines;
    }

    /**
     * Generic statutory handler — no per-scheme code branches.
     * Reads rates, ceilings, and account keys entirely from the scheme record.
     *
     * @return list<array<string, mixed>>
     */
    private function buildStatutoryLines(
        ?int $legalEntityId,
        CarbonImmutable $date,
        string $grossEarnings,
        int $startSequence,
    ): array {
        $schemes = $this->statutoryResolver->resolveStatutorySchemes($legalEntityId, $date);

        if ($schemes->isEmpty()) {
            return [];
        }

        $lines = [];
        $sequence = $startSequence;

        foreach ($schemes as $scheme) {
            $wageBase = $scheme->wage_ceiling !== null
                ? (bccomp($grossEarnings, (string) $scheme->wage_ceiling, 4) <= 0
                    ? $grossEarnings
                    : (string) $scheme->wage_ceiling)
                : $grossEarnings;

            $employeeAmount = bcmul($wageBase, bcdiv((string) $scheme->employee_rate, '100', 8), 4);
            $employerAmount = bcmul($wageBase, bcdiv((string) $scheme->employer_rate, '100', 8), 4);

            $sharedSnapshot = [
                'scheme_code' => $scheme->code,
                'scheme_name' => $scheme->name,
                'calculation_type' => $scheme->calculation_type,
                'employee_rate' => (string) $scheme->employee_rate,
                'employer_rate' => (string) $scheme->employer_rate,
                'wage_ceiling' => $scheme->wage_ceiling !== null ? (string) $scheme->wage_ceiling : null,
                'wage_base' => $wageBase,
            ];

            // Employee deduction line (type: statutory)
            $lines[] = [
                'pay_component_id' => null,
                'component_snapshot_json' => array_merge($sharedSnapshot, [
                    'side' => 'employee',
                    'type' => 'statutory',
                    'account_mapping_key' => $scheme->account_mapping_key_employee,
                ]),
                'amount' => $employeeAmount,
                'sequence' => $sequence,
            ];
            $sequence += 10;

            // Employer contribution line (type: employer_contribution)
            $lines[] = [
                'pay_component_id' => null,
                'component_snapshot_json' => array_merge($sharedSnapshot, [
                    'side' => 'employer',
                    'type' => 'employer_contribution',
                    'account_mapping_key' => $scheme->account_mapping_key_employer,
                ]),
                'amount' => $employerAmount,
                'sequence' => $sequence,
            ];
            $sequence += 10;
        }

        return $lines;
    }

    private function sumLinesByTypes(array $lineData, array $types): string
    {
        $sum = '0.0000';

        foreach ($lineData as $line) {
            $snapshot = $line['component_snapshot_json'] ?? [];
            $type = $snapshot['type'] ?? null;

            if (in_array($type, $types, true)) {
                $sum = bcadd($sum, (string) $line['amount'], 4);
            }
        }

        return $sum;
    }

    private function sumLinesByType(array $lineData, array|string $types, array $componentAmounts): string
    {
        return $this->sumLinesByTypes($lineData, (array) $types);
    }

    /**
     * @return array<string, string>
     */
    private function buildYtd(Employee $employee, PayrollRun $run): array
    {
        // Accumulate from previously processed runs in the same calendar year
        $yearStart = CarbonImmutable::parse($run->period_start)->startOfYear()->toDateString();

        $previousItems = PayrollItem::query()
            ->whereHas('payrollRun', function ($q) use ($run, $yearStart): void {
                $q->where('legal_entity_id', $run->legal_entity_id)
                    ->whereIn('status', ['draft', 'pending_approval', 'approved', 'posted'])
                    ->whereNotNull('totals_json')
                    ->where('period_start', '>=', $yearStart)
                    ->where('id', '!=', $run->id);
            })
            ->where('employee_id', $employee->id)
            ->get();

        $ytdGross = '0.0000';
        $ytdDeductions = '0.0000';
        $ytdNet = '0.0000';

        foreach ($previousItems as $item) {
            $ytdGross = bcadd($ytdGross, (string) $item->gross, 4);
            $ytdDeductions = bcadd($ytdDeductions, (string) $item->total_deductions, 4);
            $ytdNet = bcadd($ytdNet, (string) $item->net_pay, 4);
        }

        return [
            'ytd_gross' => $ytdGross,
            'ytd_deductions' => $ytdDeductions,
            'ytd_net' => $ytdNet,
        ];
    }
}
