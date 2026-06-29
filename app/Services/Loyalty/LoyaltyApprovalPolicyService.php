<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\DTOs\Loyalty\CreateLoyaltyApprovalPolicyData;
use App\DTOs\Loyalty\UpdateLoyaltyApprovalPolicyData;
use App\Models\LoyaltyApprovalPolicy;
use App\Models\LoyaltyProgram;
use App\Services\Loyalty\Concerns\AssertsLoyaltyProgramOwnership;
use Illuminate\Support\Facades\DB;

final class LoyaltyApprovalPolicyService
{
    use AssertsLoyaltyProgramOwnership;

    public function create(LoyaltyProgram $program, CreateLoyaltyApprovalPolicyData $data): LoyaltyApprovalPolicy
    {
        return DB::transaction(fn () => $program->approvalPolicies()->create([
            'action_type' => $data->actionType,
            'threshold_type' => $data->thresholdType,
            'threshold_value' => $data->thresholdValue,
            'approval_mode' => $data->approvalMode,
            'approver_role_id' => $data->approverRoleId,
            'is_active' => $data->isActive,
        ]));
    }

    public function update(
        LoyaltyProgram $program,
        LoyaltyApprovalPolicy $policy,
        UpdateLoyaltyApprovalPolicyData $data,
    ): LoyaltyApprovalPolicy {
        $this->assertBelongsToProgram($policy->program_id, $program);

        return DB::transaction(function () use ($policy, $data) {
            $policy->update([
                'action_type' => $data->actionType,
                'threshold_type' => $data->thresholdType,
                'threshold_value' => $data->thresholdValue,
                'approval_mode' => $data->approvalMode,
                'approver_role_id' => $data->approverRoleId,
                'is_active' => $data->isActive,
            ]);

            return $policy;
        });
    }

    public function delete(LoyaltyProgram $program, LoyaltyApprovalPolicy $policy): void
    {
        $this->assertBelongsToProgram($policy->program_id, $program);

        DB::transaction(fn () => $policy->delete());
    }
}
