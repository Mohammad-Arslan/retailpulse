<?php

declare(strict_types=1);

namespace App\DTOs\Leave;

final readonly class LeaveBalanceAssessment
{
    public function __construct(
        public bool $shouldBlock,
        public bool $shouldWarn,
    ) {}
}
