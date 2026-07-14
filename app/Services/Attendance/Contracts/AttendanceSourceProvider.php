<?php

declare(strict_types=1);

namespace App\Services\Attendance\Contracts;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Services\Attendance\AttendanceClockPayload;

interface AttendanceSourceProvider
{
    public function driverKey(): string;

    public function clockIn(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord;

    public function clockOut(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord;
}
