<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\DTOs\Leave\LeaveBalanceAssessment;
use App\Enums\NegativeLeaveBalancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\HrEntitySetting;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestReschedule;
use App\Models\LeaveType;
use App\Services\Hr\ApprovalApproverResolver;
use App\Services\Hr\HolidayResolver;
use App\Services\Overtime\ToilClaimService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class LeaveService
{
    private const DURATION_TYPES = ['full_day', 'half_day', 'short_leave', 'out_station'];

    private const DEFAULT_WORK_HOURS_PER_DAY = 8.0;

    private const TOIL_LEAVE_TYPE_CODE = 'TOIL';

    public function __construct(
        private readonly ApprovalApproverResolver $approvers,
        private readonly HolidayResolver $holidays,
        private readonly ToilClaimService $toilClaims,
        private readonly LeaveEligibilityService $eligibility,
    ) {}

    public function requestLeave(
        Employee $employee,
        LeaveType $leaveType,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?string $reason = null,
        string $durationType = 'full_day',
        ?string $session = null,
        ?string $startTime = null,
        ?string $endTime = null,
    ): LeaveRequest {
        $this->assertLeaveTypeActive($leaveType);
        $this->assertValidDurationType($durationType);

        if ($leaveType->code === self::TOIL_LEAVE_TYPE_CODE && ! $leaveType->allow_leave_claim) {
            throw ValidationException::withMessages([
                'leave_type_id' => __('TOIL leave claims are disabled for this leave type.'),
            ]);
        }

        if ($endDate->lessThan($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => __('The end date must be on or after the start date.'),
            ]);
        }

        if (in_array($durationType, ['half_day', 'short_leave'], true) && ! $startDate->isSameDay($endDate)) {
            throw ValidationException::withMessages([
                'end_date' => __('Half day and short leave requests must be for a single date.'),
            ]);
        }

        if ($durationType === 'half_day' && ! in_array($session, ['morning', 'afternoon'], true)) {
            throw ValidationException::withMessages([
                'session' => __('Half day requests require a morning or afternoon session.'),
            ]);
        }

        if ($durationType === 'short_leave') {
            $this->assertValidShortLeaveTimes($startTime, $endTime);
        }

        return DB::transaction(function () use (
            $employee,
            $leaveType,
            $startDate,
            $endDate,
            $reason,
            $durationType,
            $session,
            $startTime,
            $endTime,
        ): LeaveRequest {
            // Serialize concurrent submissions for this employee so the short-leave
            // monthly-quota and date-overlap checks below can't be bypassed by two
            // requests racing.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $this->assertNoOverlappingLeave(
                $employee,
                $startDate,
                $endDate,
                $durationType,
                $session,
                $startTime,
                $endTime,
            );

            $policy = $this->resolveLeavePolicy($employee, $leaveType, $startDate);

            $days = match ($durationType) {
                'half_day' => 0.5,
                'short_leave' => $this->countShortLeaveDays($employee, (string) $startTime, (string) $endTime),
                default => $this->countLeaveDays($employee, $startDate, $endDate, $policy),
            };

            if ($durationType === 'short_leave') {
                $this->assertWithinShortLeaveCaps($employee, $leaveType, $policy, (string) $startTime, (string) $endTime, $startDate);
            }

            $isToil = $leaveType->code === self::TOIL_LEAVE_TYPE_CODE;

            $deductFromBalance = $isToil || $durationType !== 'out_station'
                ? true
                : ($policy?->out_station_deducts_balance ?? false);

            $balanceWarning = false;

            if (! $isToil && $deductFromBalance && $policy !== null) {
                try {
                    $entitlement = $this->resolveEntitlement($employee, $leaveType);
                } catch (DomainException $e) {
                    throw ValidationException::withMessages([
                        'leave_type_id' => $e->getMessage(),
                    ]);
                }

                $assessment = $this->assessBalance($entitlement, $days, $policy);

                if ($assessment->shouldBlock) {
                    throw ValidationException::withMessages([
                        'days' => __('This request would exceed the available leave balance.'),
                    ]);
                }

                $balanceWarning = $assessment->shouldWarn;
            }

            $approverUserId = $this->approvers->resolveApproverUserId(
                'direct_manager',
                $employee,
                $startDate,
                'leave',
            );

            $approvalChain = [];
            if ($approverUserId !== null) {
                $approvalChain[] = [
                    'action' => 'pending',
                    'approver_user_id' => $approverUserId,
                    'strategy' => 'direct_manager',
                    'at' => now()->toIso8601String(),
                ];
            }

            $request = LeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'duration_type' => $durationType,
                'session' => $durationType === 'half_day' ? $session : null,
                'start_time' => $durationType === 'short_leave' ? $startTime : null,
                'end_time' => $durationType === 'short_leave' ? $endTime : null,
                'days' => $days,
                'deduct_from_balance' => $deductFromBalance,
                'balance_warning' => $balanceWarning,
                'reason' => $reason,
                'status' => 'pending',
                'approval_chain_json' => $approvalChain,
            ]);

            if ($isToil) {
                // TOIL is a balance source, not a duration type: full/half/short-leave
                // day-counting above is unchanged — only the balance it draws from
                // differs, so the computed `days` is converted to hours here.
                $hours = round($days * $this->resolveWorkHoursPerDay($employee), 2);
                $this->toilClaims->holdForLeaveClaim($employee, $request, $hours);
            }

            return $request;
        });
    }

    public function approve(LeaveRequest $request, int $approvedByUserId): LeaveRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $approvedByUserId): LeaveRequest {
            $request->loadMissing(['employee', 'leaveType', 'toilClaim']);

            $balanceWarning = (bool) $request->balance_warning;

            if ($request->leaveType?->code === self::TOIL_LEAVE_TYPE_CODE) {
                if ($request->toilClaim !== null) {
                    $this->toilClaims->approve($request->toilClaim, $approvedByUserId);
                }
            } elseif ($request->deduct_from_balance) {
                $entitlement = $this->resolveEntitlement(
                    $request->employee,
                    $request->leaveType,
                );

                $policy = $this->resolveLeavePolicy(
                    $request->employee,
                    $request->leaveType,
                    CarbonImmutable::parse($request->start_date),
                );

                if ($policy !== null) {
                    $assessment = $this->assessBalance($entitlement, (float) $request->days, $policy);

                    if ($assessment->shouldBlock) {
                        throw new DomainException(__('This leave request would exceed the available leave balance.'));
                    }

                    $balanceWarning = $balanceWarning || $assessment->shouldWarn;
                }

                $entitlement->increment('used_days', (float) $request->days);
            }

            $chain = is_array($request->approval_chain_json) ? $request->approval_chain_json : [];
            $chain[] = [
                'action' => 'approved',
                'by_user_id' => $approvedByUserId,
                'at' => now()->toIso8601String(),
            ];

            $request->update([
                'status' => 'approved',
                'balance_warning' => $balanceWarning,
                'approval_chain_json' => $chain,
            ]);

            return $request->fresh(['employee', 'leaveType']) ?? $request;
        });
    }

    public function reject(LeaveRequest $request, int $rejectedByUserId, ?string $reason = null): LeaveRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $rejectedByUserId, $reason): LeaveRequest {
            $request->loadMissing(['leaveType', 'toilClaim']);

            if ($request->leaveType?->code === self::TOIL_LEAVE_TYPE_CODE && $request->toilClaim !== null) {
                $this->toilClaims->reject($request->toilClaim, $rejectedByUserId, $reason);
            }

            $chain = is_array($request->approval_chain_json) ? $request->approval_chain_json : [];
            $chain[] = [
                'action' => 'rejected',
                'by_user_id' => $rejectedByUserId,
                'at' => now()->toIso8601String(),
                'reason' => $reason,
            ];

            $request->update([
                'status' => 'rejected',
                'approval_chain_json' => $chain,
            ]);

            return $request->fresh(['employee', 'leaveType']) ?? $request;
        });
    }

    public function cancel(LeaveRequest $request, int $cancelledByUserId): LeaveRequest
    {
        if ($request->status === 'approved') {
            return DB::transaction(function () use ($request, $cancelledByUserId): LeaveRequest {
                $request->loadMissing(['employee', 'leaveType', 'toilClaim']);

                if ($request->leaveType?->code === self::TOIL_LEAVE_TYPE_CODE) {
                    if ($request->toilClaim !== null) {
                        $this->toilClaims->cancel($request->toilClaim, $cancelledByUserId);
                    }
                } elseif ($request->deduct_from_balance) {
                    $entitlement = $this->resolveEntitlement(
                        $request->employee,
                        $request->leaveType,
                    );

                    $entitlement->decrement('used_days', (float) $request->days);
                }

                $chain = is_array($request->approval_chain_json) ? $request->approval_chain_json : [];
                $chain[] = [
                    'action' => 'cancelled',
                    'by_user_id' => $cancelledByUserId,
                    'at' => now()->toIso8601String(),
                ];

                $request->update([
                    'status' => 'cancelled',
                    'approval_chain_json' => $chain,
                ]);

                return $request->fresh(['employee', 'leaveType']) ?? $request;
            });
        }

        $this->assertPending($request);

        return DB::transaction(function () use ($request, $cancelledByUserId): LeaveRequest {
            $request->loadMissing(['leaveType', 'toilClaim']);

            if ($request->leaveType?->code === self::TOIL_LEAVE_TYPE_CODE && $request->toilClaim !== null) {
                $this->toilClaims->cancel($request->toilClaim, $cancelledByUserId);
            }

            $chain = is_array($request->approval_chain_json) ? $request->approval_chain_json : [];
            $chain[] = [
                'action' => 'cancelled',
                'by_user_id' => $cancelledByUserId,
                'at' => now()->toIso8601String(),
            ];

            $request->update([
                'status' => 'cancelled',
                'approval_chain_json' => $chain,
            ]);

            return $request->fresh(['employee', 'leaveType']) ?? $request;
        });
    }

    /**
     * Manager reschedule of a pending TOIL leave request. Only the dates
     * change — `days` (and therefore the TOIL hold already placed on the
     * ledger) is deliberately left untouched, so a reschedule-then-approve
     * flow can never double-touch the balance. Every reschedule is recorded
     * as an immutable audit row rather than silently overwriting the dates.
     */
    public function reschedule(
        LeaveRequest $request,
        CarbonImmutable $newStartDate,
        CarbonImmutable $newEndDate,
        int $changedByUserId,
        ?string $reason = null,
    ): LeaveRequest {
        $this->assertPending($request);

        $request->loadMissing('leaveType');

        if ($request->leaveType?->code !== self::TOIL_LEAVE_TYPE_CODE) {
            throw ValidationException::withMessages([
                'leave_type_id' => __('Only TOIL leave requests can be rescheduled.'),
            ]);
        }

        if ($newEndDate->lessThan($newStartDate)) {
            throw ValidationException::withMessages([
                'new_end_date' => __('The new end date must be on or after the new start date.'),
            ]);
        }

        if (in_array($request->duration_type, ['half_day', 'short_leave'], true) && ! $newStartDate->isSameDay($newEndDate)) {
            throw ValidationException::withMessages([
                'new_end_date' => __('Half day and short leave requests must be for a single date.'),
            ]);
        }

        return DB::transaction(function () use ($request, $newStartDate, $newEndDate, $changedByUserId, $reason): LeaveRequest {
            $request->loadMissing('employee');
            Employee::query()->whereKey($request->employee_id)->lockForUpdate()->first();

            $this->assertNoOverlappingLeave(
                $request->employee,
                $newStartDate,
                $newEndDate,
                (string) $request->duration_type,
                $request->session,
                $request->start_time,
                $request->end_time,
                $request->id,
            );

            LeaveRequestReschedule::query()->create([
                'leave_request_id' => $request->id,
                'old_start_date' => $request->start_date,
                'old_end_date' => $request->end_date,
                'new_start_date' => $newStartDate->toDateString(),
                'new_end_date' => $newEndDate->toDateString(),
                'changed_by' => $changedByUserId,
                'reason' => $reason,
            ]);

            $request->update([
                'start_date' => $newStartDate->toDateString(),
                'end_date' => $newEndDate->toDateString(),
            ]);

            return $request->fresh(['employee', 'leaveType', 'reschedules']) ?? $request;
        });
    }

    public function resolvePayrollDeductionComponent(Employee $employee, LeaveRequest $request): ?string
    {
        $request->loadMissing('leaveType');
        $leaveType = $request->leaveType;

        if ($leaveType === null || ! $leaveType->affects_payroll) {
            return null;
        }

        $entitlement = $this->findEntitlement($employee, $leaveType);
        $exceedsBalance = $leaveType->is_paid && (
            $entitlement === null
                ? (float) $request->days > 0
                : (float) $entitlement->remaining_days < 0
        );

        $needsDeduction = ! $leaveType->is_paid || $exceedsBalance;

        if (! $needsDeduction) {
            return null;
        }

        $componentCode = $leaveType->payroll_deduction_component_code;

        if ($componentCode === null || $componentCode === '') {
            throw new DomainException(__('Leave type :code requires a payroll deduction component but none is configured.', [
                'code' => $leaveType->code,
            ]));
        }

        return $componentCode;
    }

    public function resolveEntitlement(Employee $employee, LeaveType $leaveType, ?int $fiscalYearId = null): LeaveEntitlement
    {
        $entitlement = $this->findEntitlement($employee, $leaveType, $fiscalYearId);

        if ($entitlement !== null) {
            return $entitlement;
        }

        $hireDate = $employee->hire_date !== null ? CarbonImmutable::parse($employee->hire_date) : CarbonImmutable::now();
        $policy = $this->resolveLeavePolicy($employee, $leaveType, $hireDate);

        if ($policy !== null && ! $this->eligibility->isEligible($employee, $policy, CarbonImmutable::now())) {
            throw new DomainException(__(
                'This employee is not eligible for :type leave under the current policy.',
                ['type' => $leaveType->name],
            ));
        }

        return $this->createInitialEntitlement($employee, $leaveType, $policy, $hireDate, $fiscalYearId);
    }

    /**
     * Computes and persists the initial grant for a brand-new entitlement row.
     * The one and only place this math lives — shared by the lazy-fallback
     * path above and `LeaveEntitlementAssignmentService`'s explicit evaluation.
     */
    public function createInitialEntitlement(
        Employee $employee,
        LeaveType $leaveType,
        ?LeavePolicy $policy,
        CarbonImmutable $hireDate,
        ?int $fiscalYearId = null,
    ): LeaveEntitlement {
        $initialGrant = 0.0;

        if ($policy !== null && $policy->accrual_method === 'fixed_annual') {
            $initialGrant = $policy->proration_on_join
                ? $this->proratedFixedAnnualGrant($employee, (float) $policy->accrual_rate, $hireDate)
                : (float) $policy->accrual_rate;
        }

        return LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => $fiscalYearId,
            'accrued_days' => $initialGrant,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
            'accrual_last_run_on' => $hireDate->toDateString(),
        ]);
    }

    /**
     * Prorates a fixed_annual grant for a new hire, based on how much of the current
     * fiscal-year period remains from the hire date (calendar-day basis). In
     * hire_anniversary mode the employee's "year" begins exactly at the hire date, so
     * there is never a mid-year joiner — the full amount is always granted.
     */
    private function proratedFixedAnnualGrant(Employee $employee, float $accrualRate, CarbonImmutable $hireDate): float
    {
        $setting = HrEntitySetting::query()->where('legal_entity_id', $employee->legal_entity_id)->first();
        $mode = $setting?->settings_json['default_leave_fiscal_year_mode'] ?? null;
        $mode = in_array($mode, ['calendar_year', 'fiscal_year', 'hire_anniversary'], true) ? $mode : 'calendar_year';

        if ($mode === 'hire_anniversary') {
            return $accrualRate;
        }

        if ($mode === 'fiscal_year') {
            $fiscalYear = FiscalYear::query()
                ->where('legal_entity_id', $employee->legal_entity_id)
                ->where('start_date', '<=', $hireDate->toDateString())
                ->where('end_date', '>=', $hireDate->toDateString())
                ->first();

            if ($fiscalYear === null) {
                return $accrualRate;
            }

            $periodStart = CarbonImmutable::parse($fiscalYear->start_date);
            $periodEnd = CarbonImmutable::parse($fiscalYear->end_date);
        } else {
            $periodStart = CarbonImmutable::create($hireDate->year, 1, 1);
            $periodEnd = CarbonImmutable::create($hireDate->year, 12, 31);
        }

        $totalDays = $periodStart->diffInDays($periodEnd) + 1;
        $remainingDays = $hireDate->diffInDays($periodEnd) + 1;

        if ($totalDays <= 0) {
            return $accrualRate;
        }

        return round(($remainingDays / $totalDays) * $accrualRate, 2);
    }

    /**
     * Posts due monthly_accrual/per_worked_hours accrual for every active employee's
     * entitlements as of the given date. fixed_annual is skipped entirely — it is
     * granted once at first-hire (resolveEntitlement) and re-granted at year-end
     * (LeaveFiscalYearService::closeEntitlement), never incrementally here.
     *
     * @return array{processed: int, total_granted: float}
     */
    public function processAccrual(CarbonImmutable $asOf): array
    {
        $entitlements = LeaveEntitlement::query()
            ->whereHas('employee', fn ($query) => $query->where('status', 'active'))
            ->with(['employee', 'leaveType'])
            ->get();

        $processed = 0;
        $totalGranted = 0.0;

        foreach ($entitlements as $entitlement) {
            $employee = $entitlement->employee;
            $leaveType = $entitlement->leaveType;

            if ($employee === null || $leaveType === null) {
                continue;
            }

            $policy = $this->resolveLeavePolicy($employee, $leaveType, $asOf);

            if ($policy === null || $policy->accrual_method === 'fixed_annual') {
                continue;
            }

            if (! $this->eligibility->isEligible($employee, $policy, $asOf)) {
                // A scheduled batch job must not halt on one ineligible employee
                // (e.g. a policy tightened after this entitlement was created) —
                // skip it and keep processing the rest of the run.
                Log::warning('Skipped leave accrual for an entitlement whose employee is no longer eligible under the current policy.', [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'leave_policy_id' => $policy->id,
                ]);

                continue;
            }

            $granted = $this->postAccrualForEntitlement($entitlement->id, $employee, $policy, $asOf);

            if ($granted > 0.0) {
                $processed++;
                $totalGranted += $granted;
            }
        }

        return ['processed' => $processed, 'total_granted' => round($totalGranted, 2)];
    }

    private function postAccrualForEntitlement(
        int $entitlementId,
        Employee $employee,
        LeavePolicy $policy,
        CarbonImmutable $asOf,
    ): float {
        return DB::transaction(function () use ($entitlementId, $employee, $policy, $asOf): float {
            /** @var LeaveEntitlement|null $locked */
            $locked = LeaveEntitlement::query()->whereKey($entitlementId)->lockForUpdate()->first();

            if ($locked === null) {
                return 0.0;
            }

            $lastRun = $locked->accrual_last_run_on !== null
                ? CarbonImmutable::parse($locked->accrual_last_run_on)
                : ($employee->hire_date !== null ? CarbonImmutable::parse($employee->hire_date) : $asOf);

            $grant = 0.0;
            $nextRunOn = $lastRun;

            if ($policy->accrual_method === 'monthly_accrual') {
                $months = $lastRun->diffInMonths($asOf);

                if ($months >= 1) {
                    $grant = $months * (float) $policy->accrual_rate;
                    $nextRunOn = $lastRun->addMonths($months);
                }
            } elseif ($policy->accrual_method === 'per_worked_hours') {
                $minutes = (int) AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->where('status', 'closed')
                    ->where('clock_in', '>', $lastRun)
                    ->where('clock_in', '<=', $asOf)
                    ->sum('worked_minutes');

                $grant = ($minutes / 60) * (float) $policy->accrual_rate;
                $nextRunOn = $asOf;
            }

            if ($grant <= 0.0 && $nextRunOn->equalTo($lastRun)) {
                return 0.0;
            }

            $maxBalance = $policy->max_balance !== null ? (float) $policy->max_balance : null;
            $newAccrued = (float) $locked->accrued_days + $grant;

            if ($maxBalance !== null) {
                $newAccrued = min($newAccrued, $maxBalance);
            }

            $locked->update([
                'accrued_days' => round($newAccrued, 2),
                'accrual_last_run_on' => $nextRunOn->toDateString(),
            ]);

            return $grant;
        });
    }

    public function findEntitlement(
        Employee $employee,
        LeaveType $leaveType,
        ?int $fiscalYearId = null,
    ): ?LeaveEntitlement {
        return LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->when(
                $fiscalYearId === null,
                fn ($query) => $query->whereNull('fiscal_year_id'),
                fn ($query) => $query->where('fiscal_year_id', $fiscalYearId),
            )
            ->first();
    }

    public function assertLeaveTypeActive(LeaveType $leaveType): void
    {
        if ($leaveType->status !== 'active') {
            throw ValidationException::withMessages([
                'leave_type_id' => __('This leave type is not active.'),
            ]);
        }
    }

    private function assertValidDurationType(string $durationType): void
    {
        if (! in_array($durationType, self::DURATION_TYPES, true)) {
            throw ValidationException::withMessages([
                'duration_type' => __('Invalid leave duration type.'),
            ]);
        }
    }

    /**
     * Rejects a new (or rescheduled) request when the employee already has a
     * pending/approved leave whose dates conflict. Cancelled and rejected
     * requests free the dates. Complementary half-day sessions (morning +
     * afternoon) and non-overlapping short-leave windows on the same date
     * are allowed; full-day and out-station occupy the whole day.
     */
    private function assertNoOverlappingLeave(
        Employee $employee,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $durationType,
        ?string $session = null,
        ?string $startTime = null,
        ?string $endTime = null,
        ?int $excludeRequestId = null,
    ): void {
        $start = $startDate->toDateString();
        $end = $endDate->toDateString();

        $query = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);

        if ($excludeRequestId !== null) {
            $query->whereKeyNot($excludeRequestId);
        }

        foreach ($query->get() as $existing) {
            if ($this->leaveRequestsConflict(
                $durationType,
                $session,
                $startTime,
                $endTime,
                $existing,
            )) {
                throw ValidationException::withMessages([
                    'start_date' => __('This employee already has a pending or approved leave request that overlaps these dates.'),
                ]);
            }
        }
    }

    private function leaveRequestsConflict(
        string $durationType,
        ?string $session,
        ?string $startTime,
        ?string $endTime,
        LeaveRequest $existing,
    ): bool {
        $existingDuration = (string) $existing->duration_type;
        $occupiesFullDay = ['full_day', 'out_station'];

        if (in_array($durationType, $occupiesFullDay, true) || in_array($existingDuration, $occupiesFullDay, true)) {
            return true;
        }

        if ($durationType === 'half_day' && $existingDuration === 'half_day') {
            return $session !== null && $session === $existing->session;
        }

        if ($durationType === 'short_leave' && $existingDuration === 'short_leave') {
            if ($startTime === null || $endTime === null || $existing->start_time === null || $existing->end_time === null) {
                return true;
            }

            return $startTime < (string) $existing->end_time && $endTime > (string) $existing->start_time;
        }

        // half_day ↔ short_leave on the same date: treat as conflict — the
        // employee is already marked unavailable for part of that day.
        return true;
    }

    private function assertValidShortLeaveTimes(?string $startTime, ?string $endTime): void
    {
        if ($startTime === null || $endTime === null) {
            throw ValidationException::withMessages([
                'start_time' => __('Short leave requests require a start and end time.'),
            ]);
        }

        if ($endTime <= $startTime) {
            throw ValidationException::withMessages([
                'end_time' => __('The end time must be after the start time.'),
            ]);
        }
    }

    private function hoursBetween(string $startTime, string $endTime): float
    {
        return abs($this->minutesSinceMidnight($endTime) - $this->minutesSinceMidnight($startTime)) / 60;
    }

    private function minutesSinceMidnight(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }

    private function countShortLeaveDays(Employee $employee, string $startTime, string $endTime): float
    {
        $hours = $this->hoursBetween($startTime, $endTime);
        $workHoursPerDay = $this->resolveWorkHoursPerDay($employee);

        return round($hours / $workHoursPerDay, 4);
    }

    public function resolveWorkHoursPerDay(Employee $employee): float
    {
        $setting = HrEntitySetting::query()
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->first();

        $settingsJson = $setting?->settings_json ?? [];
        $value = $settingsJson['work_hours_per_day'] ?? null;

        return $value !== null && (float) $value > 0 ? (float) $value : self::DEFAULT_WORK_HOURS_PER_DAY;
    }

    private function assertWithinShortLeaveCaps(
        Employee $employee,
        LeaveType $leaveType,
        ?LeavePolicy $policy,
        string $startTime,
        string $endTime,
        CarbonImmutable $date,
    ): void {
        if ($policy === null) {
            return;
        }

        $hours = $this->hoursBetween($startTime, $endTime);

        if ($policy->short_leave_max_hours !== null && $hours > (float) $policy->short_leave_max_hours) {
            throw ValidationException::withMessages([
                'end_time' => __('Short leave cannot exceed :hours hours per request.', [
                    'hours' => rtrim(rtrim((string) $policy->short_leave_max_hours, '0'), '.'),
                ]),
            ]);
        }

        if ($policy->short_leave_max_requests_per_month !== null) {
            $count = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('duration_type', 'short_leave')
                ->whereIn('status', ['pending', 'approved'])
                ->whereYear('start_date', $date->year)
                ->whereMonth('start_date', $date->month)
                ->count();

            if ($count >= $policy->short_leave_max_requests_per_month) {
                throw ValidationException::withMessages([
                    'duration_type' => __('The monthly short leave quota of :limit requests has been reached.', [
                        'limit' => (string) $policy->short_leave_max_requests_per_month,
                    ]),
                ]);
            }
        }
    }

    private function assessBalance(LeaveEntitlement $entitlement, float $days, LeavePolicy $policy): LeaveBalanceAssessment
    {
        $overdrawnBy = $days - (float) $entitlement->remaining_days;

        if ($overdrawnBy <= 0.0) {
            return new LeaveBalanceAssessment(shouldBlock: false, shouldWarn: false);
        }

        return match ($policy->negative_leave_balance_policy ?? NegativeLeaveBalancePolicy::Block) {
            NegativeLeaveBalancePolicy::Block => new LeaveBalanceAssessment(shouldBlock: true, shouldWarn: false),
            NegativeLeaveBalancePolicy::Warn => new LeaveBalanceAssessment(shouldBlock: false, shouldWarn: true),
            NegativeLeaveBalancePolicy::Allow => new LeaveBalanceAssessment(shouldBlock: false, shouldWarn: false),
        };
    }

    private function assertPending(LeaveRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('Only pending leave requests can be updated.'),
            ]);
        }
    }

    public function resolveLeavePolicy(
        Employee $employee,
        LeaveType $leaveType,
        CarbonImmutable $date,
    ): ?LeavePolicy {
        $dateString = $date->toDateString();

        return LeavePolicy::query()
            ->where('leave_type_id', $leaveType->id)
            ->where('status', 'active')
            ->where('effective_from', '<=', $dateString)
            ->where(function ($query) use ($dateString): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $dateString);
            })
            ->where(function ($query) use ($employee): void {
                $query->whereNull('legal_entity_id')
                    ->orWhere('legal_entity_id', $employee->legal_entity_id);
            })
            ->get()
            ->sortByDesc(fn (LeavePolicy $policy): int => $policy->legal_entity_id === $employee->legal_entity_id ? 1 : 0)
            ->first();
    }

    private function countLeaveDays(
        Employee $employee,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?LeavePolicy $policy,
    ): float {
        $excludeHolidays = $policy?->exclude_public_holidays ?? true;
        $excludeWeekends = $policy?->exclude_weekends ?? true;
        $weekendDays = $excludeWeekends ? $this->resolveWeekendDays($employee) : [];
        $days = 0.0;

        for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
            if ($excludeWeekends && in_array($date->dayOfWeek, $weekendDays, true)) {
                continue;
            }
            if ($excludeHolidays && $this->holidays->isPublicHoliday($employee, $date)) {
                continue;
            }
            $days += 1;
        }

        return $days;
    }

    /**
     * Resolves the weekly off-days used to exclude weekends from leave day counts.
     *
     * Fallback chain (most specific wins): employee override (HR-configured per employee,
     * on `EmployeeShiftPreference`) → branch default → legal-entity default
     * (`HrEntitySetting.settings_json.weekend_days`) → [0, 6] (Sun/Sat) if nothing is configured.
     * An empty array at any level is a valid, deliberate "no weekly off day" configuration
     * (e.g. a departmental store branch that trades every day) — it is not treated as "unset".
     *
     * @return list<int> Carbon day-of-week values (0=Sunday … 6=Saturday)
     */
    private function resolveWeekendDays(Employee $employee): array
    {
        $employee->loadMissing('shiftPreference', 'primaryBranch');

        $shiftPreference = $employee->shiftPreference;
        if ($shiftPreference?->weekend_days_enabled) {
            return array_values(array_map('intval', $shiftPreference->weekend_days ?? []));
        }

        $branchWeekendDays = $employee->primaryBranch?->weekend_days;
        if (is_array($branchWeekendDays)) {
            return array_values(array_map('intval', $branchWeekendDays));
        }

        $setting = HrEntitySetting::query()
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->first();

        $configured = $setting?->settings_json['weekend_days'] ?? null;

        if (is_array($configured)) {
            return array_values(array_map('intval', $configured));
        }

        return [0, 6];
    }
}
