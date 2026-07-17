<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\HrEntitySetting;
use App\Models\LeaveEncashment;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveYearEndLine;
use App\Models\LeaveYearEndRun;
use App\Models\OrganizationEntity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Year-end carry-forward / expire / encash processing for leave balances.
 *
 * Distinct from `accounting:close-fiscal-year` — this operates purely on leave
 * entitlements and never touches the general ledger. It reuses the shared
 * `fiscal_years` table only as an optional reference point for entities in
 * `fiscal_year` mode; it never creates a new fiscal-year-keyed entitlement row,
 * because the rest of LeaveService (approve/cancel/request) always resolves the
 * single `fiscal_year_id = null` entitlement bucket. Instead, the existing
 * bucket is reset in place for the new year, and the closing snapshot is
 * preserved immutably in `leave_year_end_runs` / `leave_year_end_lines`.
 */
final class LeaveFiscalYearService
{
    public function __construct(
        private readonly LeaveService $leaveService,
    ) {}

    /**
     * @return list<LeaveYearEndRun>
     */
    public function processDue(CarbonImmutable $asOf): array
    {
        $runs = [];

        $entities = OrganizationEntity::query()->where('status', 'active')->get();

        foreach ($entities as $entity) {
            $mode = $this->resolveFiscalYearMode($entity);

            $runs = [
                ...$runs,
                ...match ($mode) {
                    'fiscal_year' => $this->processFiscalYearModeEntity($entity, $asOf),
                    'hire_anniversary' => $this->processHireAnniversaryModeEntity($entity, $asOf),
                    default => $this->processCalendarYearModeEntity($entity, $asOf),
                },
            ];
        }

        return $runs;
    }

    /**
     * @return list<LeaveYearEndRun>
     */
    private function processCalendarYearModeEntity(OrganizationEntity $entity, CarbonImmutable $asOf): array
    {
        if ($asOf->month !== 1 || $asOf->day !== 1) {
            return [];
        }

        $closedYear = $asOf->year - 1;
        $periodLabel = (string) $closedYear;

        if ($this->alreadyProcessed($entity, $periodLabel)) {
            return [];
        }

        $run = $this->closeYear($entity, $periodLabel, null, null, $asOf);

        return $run !== null ? [$run] : [];
    }

    /**
     * @return list<LeaveYearEndRun>
     */
    private function processFiscalYearModeEntity(OrganizationEntity $entity, CarbonImmutable $asOf): array
    {
        $closedFiscalYears = FiscalYear::query()
            ->where('legal_entity_id', $entity->id)
            ->where('end_date', '<', $asOf->toDateString())
            ->orderBy('end_date')
            ->get();

        $runs = [];

        foreach ($closedFiscalYears as $fiscalYear) {
            $periodLabel = "FY-{$fiscalYear->id}";

            if ($this->alreadyProcessed($entity, $periodLabel)) {
                continue;
            }

            $run = $this->closeYear($entity, $periodLabel, $fiscalYear, null, CarbonImmutable::parse($fiscalYear->end_date));

            if ($run !== null) {
                $runs[] = $run;
            }
        }

        return $runs;
    }

    /**
     * @return list<LeaveYearEndRun>
     */
    private function processHireAnniversaryModeEntity(OrganizationEntity $entity, CarbonImmutable $asOf): array
    {
        $employees = Employee::query()
            ->where('legal_entity_id', $entity->id)
            ->where('status', 'active')
            ->whereNotNull('hire_date')
            ->get();

        $runs = [];

        foreach ($employees as $employee) {
            $hireDate = CarbonImmutable::parse($employee->hire_date);

            if ($hireDate->month !== $asOf->month || $hireDate->day !== $asOf->day) {
                continue;
            }

            if (! $asOf->greaterThan($hireDate)) {
                continue;
            }

            $closedYear = $asOf->year - 1;
            $periodLabel = "EMP-{$employee->id}-{$closedYear}";

            if ($this->alreadyProcessed($entity, $periodLabel)) {
                continue;
            }

            $run = $this->closeYear($entity, $periodLabel, null, $employee, $asOf->subDay());

            if ($run !== null) {
                $runs[] = $run;
            }
        }

        return $runs;
    }

    private function alreadyProcessed(OrganizationEntity $entity, string $periodLabel): bool
    {
        return LeaveYearEndRun::query()
            ->where('legal_entity_id', $entity->id)
            ->where('period_label', $periodLabel)
            ->exists();
    }

    /**
     * Close the year for one legal entity (optionally scoped to a single employee,
     * for hire_anniversary mode). Wrapped in a single transaction so a run is
     * either fully recorded or not recorded at all.
     */
    private function closeYear(
        OrganizationEntity $entity,
        string $periodLabel,
        ?FiscalYear $fiscalYear,
        ?Employee $onlyEmployee,
        CarbonImmutable $periodEnd,
    ): ?LeaveYearEndRun {
        return DB::transaction(function () use ($entity, $periodLabel, $fiscalYear, $onlyEmployee, $periodEnd): ?LeaveYearEndRun {
            // Re-check under transaction to close a race between two overlapping schedule runs.
            if ($this->alreadyProcessed($entity, $periodLabel)) {
                return null;
            }

            $entitlementsQuery = LeaveEntitlement::query()
                ->whereHas('employee', function ($q) use ($entity, $onlyEmployee): void {
                    $q->where('legal_entity_id', $entity->id)->where('status', 'active');
                    if ($onlyEmployee !== null) {
                        $q->where('id', $onlyEmployee->id);
                    }
                })
                ->with(['employee', 'leaveType'])
                ->lockForUpdate()
                ->get();

            $run = LeaveYearEndRun::query()->create([
                'legal_entity_id' => $entity->id,
                'fiscal_year_id' => $fiscalYear?->id,
                'employee_id' => $onlyEmployee?->id,
                'period_label' => $periodLabel,
                'status' => 'completed',
                'executed_at' => now(),
            ]);

            $totals = ['carried_forward' => 0.0, 'expired' => 0.0, 'encashed' => 0.0, 'entitlements_processed' => 0];

            foreach ($entitlementsQuery as $entitlement) {
                $line = $this->closeEntitlement($run, $entitlement, $periodEnd);

                if ($line === null) {
                    continue;
                }

                $totals['carried_forward'] += (float) $line->carried_forward;
                $totals['expired'] += (float) $line->expired;
                $totals['encashed'] += (float) $line->encashed;
                $totals['entitlements_processed']++;
            }

            $run->update(['totals_json' => $totals]);

            return $run->fresh(['lines']) ?? $run;
        });
    }

    private function closeEntitlement(LeaveYearEndRun $run, LeaveEntitlement $entitlement, CarbonImmutable $periodEnd): ?LeaveYearEndLine
    {
        $employee = $entitlement->employee;
        $leaveType = $entitlement->leaveType;

        if ($employee === null || $leaveType === null) {
            return null;
        }

        $policy = $this->leaveService->resolveLeavePolicy($employee, $leaveType, $periodEnd);

        if ($policy === null) {
            // No policy governs this leave type for this employee — nothing to carry/expire by rule.
            return null;
        }

        $pendingHold = $this->resolvePendingHold($employee, $leaveType);
        $remaining = max(0.0, (float) $entitlement->remaining_days);
        $availableForDisposition = max(0.0, $remaining - $pendingHold);

        $limit = $policy->carry_forward_limit !== null ? (float) $policy->carry_forward_limit : null;
        $carryFromAvailable = $limit !== null ? min($availableForDisposition, $limit) : $availableForDisposition;
        $excess = $availableForDisposition - $carryFromAvailable;

        $encashedAmount = 0.0;
        $expiredAmount = 0.0;

        if ($excess > 0.0) {
            if ($policy->year_end_excess_disposition === 'encash'
                && $policy->encashment_allowed
                && $leaveType->payroll_encashment_component_code !== null
                && $leaveType->payroll_encashment_component_code !== ''
            ) {
                $encashedAmount = $excess;
                $this->recordAutomaticEncashment($run, $employee, $leaveType, $policy, $encashedAmount);
            } else {
                $expiredAmount = $excess;
            }
        }

        $totalCarried = $carryFromAvailable + $pendingHold;

        $entitlement->update([
            'accrued_days' => 0,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => $totalCarried,
        ]);

        return LeaveYearEndLine::query()->create([
            'leave_year_end_run_id' => $run->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'carried_forward' => $totalCarried,
            'expired' => $expiredAmount,
            'encashed' => $encashedAmount,
            'next_opening' => $totalCarried,
        ]);
    }

    private function resolvePendingHold(Employee $employee, LeaveType $leaveType): float
    {
        return (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('status', 'pending')
            ->where('deduct_from_balance', true)
            ->sum('days');
    }

    private function recordAutomaticEncashment(
        LeaveYearEndRun $run,
        Employee $employee,
        LeaveType $leaveType,
        LeavePolicy $policy,
        float $days,
    ): void {
        LeaveEncashment::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_policy_id' => $policy->id,
            'fiscal_year_id' => $run->fiscal_year_id,
            'days' => $days,
            'payroll_component_code' => $leaveType->payroll_encashment_component_code,
            'reason' => __('Automatic year-end encashment of excess leave above the carry-forward limit.'),
            'status' => 'approved',
            'approved_at' => now(),
            'approval_chain_json' => [[
                'action' => 'auto_approved',
                'reason' => 'year_end_excess_disposition',
                'at' => now()->toIso8601String(),
            ]],
        ]);
    }

    private function resolveFiscalYearMode(OrganizationEntity $entity): string
    {
        $setting = HrEntitySetting::query()->where('legal_entity_id', $entity->id)->first();
        $mode = $setting?->settings_json['default_leave_fiscal_year_mode'] ?? null;

        return in_array($mode, ['calendar_year', 'fiscal_year', 'hire_anniversary'], true) ? $mode : 'calendar_year';
    }
}
