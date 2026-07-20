<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

final readonly class ConsumedCost
{
    public function __construct(
        public float $amount,
        public bool $estimated,
        public string $basis,
    ) {}
}
