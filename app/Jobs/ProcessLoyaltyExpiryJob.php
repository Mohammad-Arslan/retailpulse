<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Loyalty\LoyaltyExpiryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessLoyaltyExpiryJob implements ShouldQueue
{
    use Queueable;

    public function handle(LoyaltyExpiryService $expiry): void
    {
        $expiry->processAllPrograms();
    }
}
