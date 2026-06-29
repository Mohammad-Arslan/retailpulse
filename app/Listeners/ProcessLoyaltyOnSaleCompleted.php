<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Services\Loyalty\LoyaltyEarnService;

final class ProcessLoyaltyOnSaleCompleted
{
    public function __construct(
        private readonly LoyaltyEarnService $loyaltyEarn,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $this->loyaltyEarn->earnOnSaleComplete($event->sale);
    }
}
