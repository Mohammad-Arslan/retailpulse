<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use RuntimeException;

final class UnsupportedAttendanceDriverException extends RuntimeException
{
    public function __construct(string $driverKey)
    {
        parent::__construct("Attendance driver [{$driverKey}] is not supported.");
    }
}
