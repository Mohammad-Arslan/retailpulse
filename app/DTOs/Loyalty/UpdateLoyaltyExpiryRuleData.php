<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Enums\LoyaltyExpiryType;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyExpiryRuleRequest;

final readonly class UpdateLoyaltyExpiryRuleData
{
    public function __construct(
        public LoyaltyExpiryType $expiryType,
        public ?int $value,
        public int $gracePeriodDays,
    ) {}

    public static function fromRequest(UpdateLoyaltyExpiryRuleRequest $request): self
    {
        $value = $request->validated('value');

        return new self(
            expiryType: LoyaltyExpiryType::from($request->validated('expiry_type')),
            value: $value !== null ? (int) $value : null,
            gracePeriodDays: (int) $request->validated('grace_period_days'),
        );
    }
}
