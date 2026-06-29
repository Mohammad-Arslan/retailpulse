<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Enums\LoyaltyTierQualificationType;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyProgramTierRequest;

final readonly class CreateLoyaltyProgramTierData
{
    /**
     * @param  array<string, mixed>|null  $benefitsJson
     */
    public function __construct(
        public string $name,
        public int $tierLevel,
        public LoyaltyTierQualificationType $qualificationType,
        public float $qualificationValue,
        public float $multiplier,
        public ?array $benefitsJson,
        public string $status,
    ) {}

    public static function fromRequest(StoreLoyaltyProgramTierRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            tierLevel: (int) $request->validated('tier_level'),
            qualificationType: LoyaltyTierQualificationType::from($request->validated('qualification_type')),
            qualificationValue: (float) $request->validated('qualification_value'),
            multiplier: (float) $request->validated('multiplier'),
            benefitsJson: $request->validated('benefits_json'),
            status: $request->validated('status'),
        );
    }
}
