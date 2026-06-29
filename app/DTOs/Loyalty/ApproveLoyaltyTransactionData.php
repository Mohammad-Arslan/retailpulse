<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Http\Requests\Admin\Loyalty\ApproveLoyaltyTransactionRequest;

final readonly class ApproveLoyaltyTransactionData
{
    public function __construct(
        public string $pin,
    ) {}

    public static function fromRequest(ApproveLoyaltyTransactionRequest $request): self
    {
        return new self(
            pin: $request->validated('pin'),
        );
    }
}
