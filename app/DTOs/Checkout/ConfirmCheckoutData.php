<?php

declare(strict_types=1);

namespace App\DTOs\Checkout;

final readonly class ConfirmCheckoutData
{
    public function __construct(
        public ?int $customerId = null,
        public ?string $notes = null,
        public int $loyaltyPointsToRedeem = 0,
    ) {}
}
