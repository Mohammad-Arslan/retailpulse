<?php

declare(strict_types=1);

namespace App\Services\Attendance\Providers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Services\Attendance\AttendanceClockPayload;
use App\Services\Attendance\Contracts\AttendanceSourceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

abstract class AbstractAttendanceProvider implements AttendanceSourceProvider
{
    protected function createClockIn(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        $at = $payload->at ?? CarbonImmutable::now();

        return AttendanceRecord::query()->create([
            'employee_id' => $payload->employeeId,
            'branch_id' => $payload->branchId,
            'source_id' => $source->id,
            'clock_in' => $at,
            'status' => 'open',
        ]);
    }

    protected function completeClockOut(AttendanceRecord $record, CarbonImmutable $at): AttendanceRecord
    {
        if ($record->status !== 'open') {
            throw ValidationException::withMessages([
                'record' => __('This attendance record is not open for clock-out.'),
            ]);
        }

        if ($at->lessThan($record->clock_in)) {
            throw ValidationException::withMessages([
                'clock_out' => __('Clock-out time must be after clock-in time.'),
            ]);
        }

        $record->update([
            'clock_out' => $at,
            'worked_minutes' => (int) $record->clock_in->diffInMinutes($at),
            'status' => 'closed',
        ]);

        return $record->fresh() ?? $record;
    }

    protected function resolveOpenRecord(AttendanceClockPayload $payload): AttendanceRecord
    {
        if ($payload->openRecordId !== null) {
            $record = AttendanceRecord::query()
                ->whereKey($payload->openRecordId)
                ->where('employee_id', $payload->employeeId)
                ->where('branch_id', $payload->branchId)
                ->where('status', 'open')
                ->first();

            if ($record === null) {
                throw ValidationException::withMessages([
                    'record' => __('No open attendance record was found for clock-out.'),
                ]);
            }

            return $record;
        }

        $record = AttendanceRecord::query()
            ->where('employee_id', $payload->employeeId)
            ->where('branch_id', $payload->branchId)
            ->where('status', 'open')
            ->orderByDesc('clock_in')
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'record' => __('No open attendance record was found for clock-out.'),
            ]);
        }

        return $record;
    }
}
