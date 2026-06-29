<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyApprovalMode;
use App\Enums\LoyaltyApprovalThresholdType;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyApprovalPolicyRequest;

final readonly class UpdateLoyaltyApprovalPolicyData
{
    public function __construct(
        public LoyaltyApprovalActionType $actionType,
        public LoyaltyApprovalThresholdType $thresholdType,
        public float $thresholdValue,
        public LoyaltyApprovalMode $approvalMode,
        public ?int $approverRoleId,
        public bool $isActive,
    ) {}

    public static function fromRequest(UpdateLoyaltyApprovalPolicyRequest $request): self
    {
        $approverRoleId = $request->validated('approver_role_id');

        return new self(
            actionType: LoyaltyApprovalActionType::from($request->validated('action_type')),
            thresholdType: LoyaltyApprovalThresholdType::from($request->validated('threshold_type')),
            thresholdValue: (float) $request->validated('threshold_value'),
            approvalMode: LoyaltyApprovalMode::from($request->validated('approval_mode')),
            approverRoleId: $approverRoleId !== null ? (int) $approverRoleId : null,
            isActive: $request->boolean('is_active', true),
        );
    }
}
