<?php

declare(strict_types=1);

namespace App\Services\Overtime;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRecord;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Config-driven overtime calculation engine.
 * Thresholds and multipliers are resolved from overtime_policies / overtime_multipliers only.
 */
final class OvertimeEngine
{
    public const DAY_TYPE_WEEKDAY = 'weekday';

    public const DAY_TYPE_WEEKEND = 'weekend';

    public const DAY_TYPE_REST_DAY = 'rest_day';

    public const DAY_TYPE_PUBLIC_HOLIDAY = 'public_holiday';

    public function resolvePolicy(
        Employee $employee,
        ?int $branchId,
        ?int $legalEntityId,
        CarbonImmutable $date,
    ): ?OvertimePolicy {
        $branchId ??= $employee->primary_branch_id;
        $legalEntityId ??= $employee->legal_entity_id;
        $dateString = $date->toDateString();

        /** @var Collection<int, OvertimePolicy> $policies */
        $policies = OvertimePolicy::query()
            ->where('status', 'active')
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where(function ($query) use ($legalEntityId): void {
                $query->whereNull('legal_entity_id')->orWhere('legal_entity_id', $legalEntityId);
            })
            ->where('effective_from', '<=', $dateString)
            ->where(function ($query) use ($dateString): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $dateString);
            })
            ->get();

        if ($policies->isEmpty()) {
            return null;
        }

        return $policies
            ->sortByDesc(fn (OvertimePolicy $policy): int => $this->specificityScore($policy))
            ->first();
    }

    public function resolveDayType(CarbonImmutable $date, ?string $override = null): string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }

        return $date->isWeekend() ? self::DAY_TYPE_WEEKEND : self::DAY_TYPE_WEEKDAY;
    }

    public function resolveMultiplier(OvertimePolicy $policy, string $dayType): ?string
    {
        $policy->loadMissing('multipliers');

        $multiplier = $policy->multipliers->firstWhere('day_type', $dayType);

        if ($multiplier === null) {
            return null;
        }

        return (string) $multiplier->multiplier;
    }

    public function calculateFromAttendance(
        Employee $employee,
        CarbonImmutable $date,
        ?int $branchId = null,
        ?string $dayType = null,
    ): ?OvertimeRecord {
        $branchId ??= $employee->primary_branch_id;

        $workedMinutes = (int) AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('clock_in', $date->toDateString())
            ->where('status', 'closed')
            ->sum('worked_minutes');

        if ($workedMinutes <= 0) {
            return null;
        }

        return $this->createRecord($employee, $date, $workedMinutes, $branchId, $dayType);
    }

    public function createRecord(
        Employee $employee,
        CarbonImmutable $date,
        int $workedMinutes,
        ?int $branchId = null,
        ?string $dayType = null,
    ): OvertimeRecord {
        if ($workedMinutes < 0) {
            throw ValidationException::withMessages([
                'worked_minutes' => __('Worked minutes cannot be negative.'),
            ]);
        }

        $policy = $this->resolvePolicy($employee, $branchId, $employee->legal_entity_id, $date);

        if ($policy === null) {
            throw new DomainException(__('No active overtime policy found for this employee and date.'));
        }

        $resolvedDayType = $this->resolveDayType($date, $dayType);
        $multiplier = $this->resolveMultiplier($policy, $resolvedDayType);

        if ($multiplier === null) {
            throw new DomainException(__('No overtime multiplier configured for day type :type.', [
                'type' => $resolvedDayType,
            ]));
        }

        $threshold = (int) $policy->daily_threshold_minutes;
        $regularMinutes = min($workedMinutes, $threshold);
        $overtimeMinutes = max(0, $workedMinutes - $threshold);

        /** @var OvertimeRecord $record */
        $record = OvertimeRecord::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $date->toDateString(),
            ],
            [
                'regular_minutes' => $regularMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'day_type' => $resolvedDayType,
                'resolved_multiplier' => $multiplier,
                'overtime_policy_id' => $policy->id,
                'status' => 'pending',
                'approved_by' => null,
            ],
        );

        return $record->fresh(['employee', 'policy']) ?? $record;
    }

    public function approveRecord(OvertimeRecord $record, int $approvedByUserId): OvertimeRecord
    {
        $this->assertPending($record);

        $record->update([
            'status' => 'approved',
            'approved_by' => $approvedByUserId,
        ]);

        return $record->fresh(['employee', 'policy']) ?? $record;
    }

    public function rejectRecord(OvertimeRecord $record, int $rejectedByUserId): OvertimeRecord
    {
        $this->assertPending($record);

        $record->update([
            'status' => 'rejected',
            'approved_by' => $rejectedByUserId,
        ]);

        return $record->fresh(['employee', 'policy']) ?? $record;
    }

    /**
     * @return Collection<int, OvertimeRecord>
     */
    public function approvedRecordsForPeriod(
        Employee $employee,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        return OvertimeRecord::query()
            ->with(['policy'])
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get();
    }

    public function calculatePayUnits(OvertimeRecord $record): string
    {
        return bcmul(
            (string) $record->overtime_minutes,
            (string) $record->resolved_multiplier,
            4,
        );
    }

    private function specificityScore(OvertimePolicy $policy): int
    {
        $score = 1000 - (int) $policy->priority;

        if ($policy->legal_entity_id !== null) {
            $score += 150;
        }

        if ($policy->branch_id !== null) {
            $score += 100;
        }

        return $score;
    }

    private function assertPending(OvertimeRecord $record): void
    {
        if ($record->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('Only pending overtime records can be updated.'),
            ]);
        }
    }
}
