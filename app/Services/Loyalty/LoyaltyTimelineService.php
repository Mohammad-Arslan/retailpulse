<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyEventType;
use App\Models\CustomerLoyaltyEvent;
use App\Models\LoyaltyProgram;

final class LoyaltyTimelineService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        int $customerId,
        LoyaltyProgram $program,
        LoyaltyEventType $eventType,
        int $points,
        int $beforeBalance,
        int $afterBalance,
        ?string $description = null,
        ?array $metadata = null,
        ?int $userId = null,
    ): CustomerLoyaltyEvent {
        return CustomerLoyaltyEvent::query()->create([
            'customer_id' => $customerId,
            'program_id' => $program->id,
            'event_type' => $eventType,
            'points' => $points,
            'before_balance' => $beforeBalance,
            'after_balance' => $afterBalance,
            'description' => $description,
            'metadata_json' => $metadata,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
