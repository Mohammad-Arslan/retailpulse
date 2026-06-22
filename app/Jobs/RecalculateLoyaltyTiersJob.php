<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Customer\LoyaltyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RecalculateLoyaltyTiersJob implements ShouldQueue
{
    use Queueable;

    public function handle(LoyaltyService $loyalty): void
    {
        Customer::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(200, function ($customers) use ($loyalty) {
                foreach ($customers as $customer) {
                    $loyalty->recalculateTier($customer);
                }
            });
    }
}
