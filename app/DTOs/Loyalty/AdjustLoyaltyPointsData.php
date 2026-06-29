<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Http\Requests\Admin\Loyalty\AdjustLoyaltyPointsRequest;

final readonly class AdjustLoyaltyPointsData
{
    public function __construct(
        public int $programId,
        public int $points,
        public string $reason,
    ) {}

    public static function fromRequest(AdjustLoyaltyPointsRequest $request): self
    {
        return new self(
            programId: (int) $request->validated('program_id'),
            points: (int) $request->validated('points'),
            reason: $request->validated('reason'),
        );
    }
}
