<?php

declare(strict_types=1);

namespace App\Services\Attendance\Providers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Services\Attendance\AttendanceClockPayload;
use Carbon\CarbonImmutable;

final class PosPinAttendanceProvider extends AbstractAttendanceProvider
{
    public function driverKey(): string
    {
        return 'pos_pin';
    }

    public function clockIn(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        return $this->createClockIn($source, $payload);
    }

    public function clockOut(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        $record = $this->resolveOpenRecord($payload);
        $at = $payload->at ?? CarbonImmutable::now();

        return $this->completeClockOut($record, $at);
    }
}
