<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Enums\LoyaltyRuleType;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyRuleRequest;

final readonly class CreateLoyaltyRuleData
{
    /**
     * @param  array<string, mixed>|null  $conditionsJson
     * @param  array<string, mixed>|null  $rewardJson
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public LoyaltyRuleType $ruleType,
        public int $priority,
        public ?array $conditionsJson,
        public ?array $rewardJson,
        public bool $isActive,
        public ?string $effectiveFrom,
        public ?string $effectiveTo,
    ) {}

    public static function fromRequest(StoreLoyaltyRuleRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            ruleType: LoyaltyRuleType::from($request->validated('rule_type')),
            priority: (int) $request->validated('priority'),
            conditionsJson: $request->validated('conditions_json'),
            rewardJson: $request->validated('reward_json'),
            isActive: $request->boolean('is_active', true),
            effectiveFrom: $request->validated('effective_from'),
            effectiveTo: $request->validated('effective_to'),
        );
    }
}
