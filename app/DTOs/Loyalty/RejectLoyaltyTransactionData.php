<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Http\Requests\Admin\Loyalty\RejectLoyaltyTransactionRequest;

final readonly class RejectLoyaltyTransactionData
{
    public function __construct(
        public ?string $reason,
    ) {}

    public static function fromRequest(RejectLoyaltyTransactionRequest $request): self
    {
        return new self(
            reason: $request->validated('reason'),
        );
    }
}
