<?php

declare(strict_types=1);

namespace App\Services\Attendance\Providers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Services\Attendance\AttendanceClockPayload;
use App\Services\Attendance\Contracts\AttendanceSourceProvider;
use App\Services\Attendance\UnsupportedAttendanceDriverException;

final class UnsupportedAttendanceProvider implements AttendanceSourceProvider
{
    public function __construct(
        private readonly string $driverKey,
    ) {}

    public function driverKey(): string
    {
        return $this->driverKey;
    }

    public function clockIn(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        throw new UnsupportedAttendanceDriverException($this->driverKey);
    }

    public function clockOut(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        throw new UnsupportedAttendanceDriverException($this->driverKey);
    }
}
