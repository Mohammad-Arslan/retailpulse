<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\HrEntitySetting;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\ApprovalApproverResolver;
use App\Services\Hr\HolidayResolver;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveService
{
    private const DURATION_TYPES = ['full_day', 'half_day', 'short_leave', 'out_station'];

    private const DEFAULT_WORK_HOURS_PER_DAY = 8.0;

    public function __construct(
        private readonly ApprovalApproverResolver $approvers,
        private readonly HolidayResolver $holidays,
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
            // monthly-quota check below can't be bypassed by two requests racing.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $policy = $this->resolveLeavePolicy($employee, $leaveType, $startDate);

            $days = match ($durationType) {
                'half_day' => 0.5,
                'short_leave' => $this->countShortLeaveDays($employee, (string) $startTime, (string) $endTime),
                default => $this->countLeaveDays($employee, $startDate, $endDate, $policy),
            };

            if ($durationType === 'short_leave') {
                $this->assertWithinShortLeaveCaps($employee, $leaveType, $policy, (string) $startTime, (string) $endTime, $startDate);
            }

            $deductFromBalance = $durationType === 'out_station'
                ? ($policy?->out_station_deducts_balance ?? false)
                : true;

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

            return LeaveRequest::query()->create([
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
                'reason' => $reason,
                'status' => 'pending',
                'approval_chain_json' => $approvalChain,
            ]);
        });
    }

    public function approve(LeaveRequest $request, int $approvedByUserId): LeaveRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $approvedByUserId): LeaveRequest {
            $request->loadMissing(['employee', 'leaveType']);

            if ($request->deduct_from_balance) {
                $entitlement = $this->resolveEntitlement(
                    $request->employee,
                    $request->leaveType,
                );

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
                'approval_chain_json' => $chain,
            ]);

            return $request->fresh(['employee', 'leaveType']) ?? $request;
        });
    }

    public function reject(LeaveRequest $request, int $rejectedByUserId, ?string $reason = null): LeaveRequest
    {
        $this->assertPending($request);

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
    }

    public function cancel(LeaveRequest $request, int $cancelledByUserId): LeaveRequest
    {
        if ($request->status === 'approved') {
            return DB::transaction(function () use ($request, $cancelledByUserId): LeaveRequest {
                $request->loadMissing(['employee', 'leaveType']);

                if ($request->deduct_from_balance) {
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

        return LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => $fiscalYearId,
            'accrued_days' => 0,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);
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

    private function resolveWorkHoursPerDay(Employee $employee): float
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
     * @return list<int> Carbon day-of-week values (0=Sunday … 6=Saturday)
     */
    private function resolveWeekendDays(Employee $employee): array
    {
        $setting = HrEntitySetting::query()
            ->where('legal_entity_id', $employee->legal_entity_id)
            ->first();

        $configured = $setting?->settings_json['weekend_days'] ?? null;

        if (is_array($configured) && $configured !== []) {
            return array_values(array_map('intval', $configured));
        }

        return [0, 6];
    }
}
