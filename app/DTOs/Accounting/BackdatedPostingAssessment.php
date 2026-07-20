<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

final readonly class BackdatedPostingAssessment
{
    public function __construct(
        public bool $isBackdated,
        public bool $shouldBlock,
        public bool $shouldFlag,
    ) {}
}
