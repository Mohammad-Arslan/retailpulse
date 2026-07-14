<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;

final readonly class AttendanceClockPayload
{
    public function __construct(
        public int $employeeId,
        public int $branchId,
        public ?CarbonImmutable $at = null,
        public ?int $openRecordId = null,
    ) {}
}
