<?php

declare(strict_types=1);

namespace App\DTOs\Checkout;

final readonly class AddPaymentData
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $method,
        public float $amount,
        public ?float $tenderedAmount = null,
        public array $meta = [],
    ) {}
}
