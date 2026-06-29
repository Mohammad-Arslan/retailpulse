<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Enums\LoyaltyProgramScopeType;
use App\Enums\LoyaltyScopeMode;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyProgramRequest;

final readonly class UpdateLoyaltyProgramData
{
    /**
     * @param  list<int>|null  $branchIds
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public LoyaltyProgramScopeType $scopeType,
        public LoyaltyScopeMode $earnScope,
        public LoyaltyScopeMode $redeemScope,
        public bool $allowCrossBranchEarn,
        public bool $allowCrossBranchRedeem,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?array $branchIds,
    ) {}

    public static function fromRequest(UpdateLoyaltyProgramRequest $request): self
    {
        $branchIds = $request->validated('branch_ids');

        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            scopeType: LoyaltyProgramScopeType::from($request->validated('scope_type')),
            earnScope: LoyaltyScopeMode::from($request->validated('earn_scope')),
            redeemScope: LoyaltyScopeMode::from($request->validated('redeem_scope')),
            allowCrossBranchEarn: $request->boolean('allow_cross_branch_earn', true),
            allowCrossBranchRedeem: $request->boolean('allow_cross_branch_redeem', true),
            startsAt: $request->validated('starts_at'),
            endsAt: $request->validated('ends_at'),
            branchIds: is_array($branchIds) ? array_map(intval(...), $branchIds) : null,
        );
    }
}
