<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Enums\LoyaltyCampaignStatus;
use App\Enums\LoyaltyCampaignType;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyCampaignRequest;

final readonly class CreateLoyaltyCampaignData
{
    /**
     * @param  array<string, mixed>|null  $configurationJson
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public LoyaltyCampaignType $campaignType,
        public ?array $configurationJson,
        public ?string $startsAt,
        public ?string $endsAt,
        public LoyaltyCampaignStatus $status,
    ) {}

    public static function fromRequest(StoreLoyaltyCampaignRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            campaignType: LoyaltyCampaignType::from($request->validated('campaign_type')),
            configurationJson: $request->validated('configuration_json'),
            startsAt: $request->validated('starts_at'),
            endsAt: $request->validated('ends_at'),
            status: LoyaltyCampaignStatus::from($request->validated('status')),
        );
    }
}
