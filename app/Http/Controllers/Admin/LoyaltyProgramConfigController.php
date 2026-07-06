<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Loyalty\CreateLoyaltyApprovalPolicyData;
use App\DTOs\Loyalty\CreateLoyaltyCampaignData;
use App\DTOs\Loyalty\CreateLoyaltyExpiryRuleData;
use App\DTOs\Loyalty\CreateLoyaltyProgramTierData;
use App\DTOs\Loyalty\CreateLoyaltyRuleData;
use App\DTOs\Loyalty\UpdateLoyaltyApprovalPolicyData;
use App\DTOs\Loyalty\UpdateLoyaltyCampaignData;
use App\DTOs\Loyalty\UpdateLoyaltyExpiryRuleData;
use App\DTOs\Loyalty\UpdateLoyaltyProgramTierData;
use App\DTOs\Loyalty\UpdateLoyaltyRuleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyApprovalPolicyRequest;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyCampaignRequest;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyExpiryRuleRequest;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyProgramTierRequest;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyRuleRequest;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyApprovalPolicyRequest;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyCampaignRequest;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyExpiryRuleRequest;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyProgramTierRequest;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyRuleRequest;
use App\Models\LoyaltyApprovalPolicy;
use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyExpiryRule;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramTier;
use App\Models\LoyaltyRule;
use App\Services\Loyalty\LoyaltyApprovalPolicyService;
use App\Services\Loyalty\LoyaltyCampaignService;
use App\Services\Loyalty\LoyaltyExpiryRuleService;
use App\Services\Loyalty\LoyaltyProgramTierService;
use App\Services\Loyalty\LoyaltyRuleService;
use Illuminate\Http\RedirectResponse;

final class LoyaltyProgramConfigController extends Controller
{
    public function __construct(
        private readonly LoyaltyRuleService $rules,
        private readonly LoyaltyProgramTierService $tiers,
        private readonly LoyaltyApprovalPolicyService $approvalPolicies,
        private readonly LoyaltyExpiryRuleService $expiryRules,
        private readonly LoyaltyCampaignService $campaigns,
    ) {}

    public function storeRule(StoreLoyaltyRuleRequest $request, LoyaltyProgram $program): RedirectResponse
    {
        $this->authorize('manageRules', LoyaltyProgram::class);

        $this->rules->create($program, CreateLoyaltyRuleData::fromRequest($request));

        return $this->backToTab($program, 'rules', __('Loyalty rule created.'));
    }

    public function updateRule(UpdateLoyaltyRuleRequest $request, LoyaltyProgram $program, LoyaltyRule $rule): RedirectResponse
    {
        $this->authorize('manageRules', LoyaltyProgram::class);

        $this->rules->update($program, $rule, UpdateLoyaltyRuleData::fromRequest($request));

        return $this->backToTab($program, 'rules', __('Loyalty rule updated.'));
    }

    public function destroyRule(LoyaltyProgram $program, LoyaltyRule $rule): RedirectResponse
    {
        $this->authorize('manageRules', LoyaltyProgram::class);

        $this->rules->delete($program, $rule);

        return $this->backToTab($program, 'rules', __('Loyalty rule deleted.'));
    }

    public function storeTier(StoreLoyaltyProgramTierRequest $request, LoyaltyProgram $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->tiers->create($program, CreateLoyaltyProgramTierData::fromRequest($request));

        return $this->backToTab($program, 'tiers', __('Loyalty tier created.'));
    }

    public function updateTier(
        UpdateLoyaltyProgramTierRequest $request,
        LoyaltyProgram $program,
        LoyaltyProgramTier $tier,
    ): RedirectResponse {
        $this->authorize('update', $program);

        $this->tiers->update($program, $tier, UpdateLoyaltyProgramTierData::fromRequest($request));

        return $this->backToTab($program, 'tiers', __('Loyalty tier updated.'));
    }

    public function destroyTier(LoyaltyProgram $program, LoyaltyProgramTier $tier): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->tiers->delete($program, $tier);

        return $this->backToTab($program, 'tiers', __('Loyalty tier deleted.'));
    }

    public function storeApprovalPolicy(StoreLoyaltyApprovalPolicyRequest $request, LoyaltyProgram $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->approvalPolicies->create($program, CreateLoyaltyApprovalPolicyData::fromRequest($request));

        return $this->backToTab($program, 'approvals', __('Approval policy created.'));
    }

    public function updateApprovalPolicy(
        UpdateLoyaltyApprovalPolicyRequest $request,
        LoyaltyProgram $program,
        LoyaltyApprovalPolicy $policy,
    ): RedirectResponse {
        $this->authorize('update', $program);

        $this->approvalPolicies->update($program, $policy, UpdateLoyaltyApprovalPolicyData::fromRequest($request));

        return $this->backToTab($program, 'approvals', __('Approval policy updated.'));
    }

    public function destroyApprovalPolicy(LoyaltyProgram $program, LoyaltyApprovalPolicy $policy): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->approvalPolicies->delete($program, $policy);

        return $this->backToTab($program, 'approvals', __('Approval policy deleted.'));
    }

    public function storeExpiryRule(StoreLoyaltyExpiryRuleRequest $request, LoyaltyProgram $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->expiryRules->create($program, CreateLoyaltyExpiryRuleData::fromRequest($request));

        return $this->backToTab($program, 'expiry', __('Expiry rule created.'));
    }

    public function updateExpiryRule(
        UpdateLoyaltyExpiryRuleRequest $request,
        LoyaltyProgram $program,
        LoyaltyExpiryRule $expiryRule,
    ): RedirectResponse {
        $this->authorize('update', $program);

        $this->expiryRules->update($program, $expiryRule, UpdateLoyaltyExpiryRuleData::fromRequest($request));

        return $this->backToTab($program, 'expiry', __('Expiry rule updated.'));
    }

    public function destroyExpiryRule(LoyaltyProgram $program, LoyaltyExpiryRule $expiryRule): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->expiryRules->delete($program, $expiryRule);

        return $this->backToTab($program, 'expiry', __('Expiry rule deleted.'));
    }

    public function storeCampaign(StoreLoyaltyCampaignRequest $request, LoyaltyProgram $program): RedirectResponse
    {
        $this->authorize('manageCampaigns', LoyaltyProgram::class);

        $this->campaigns->create($program, CreateLoyaltyCampaignData::fromRequest($request));

        return $this->backToTab($program, 'campaigns', __('Campaign created.'));
    }

    public function updateCampaign(
        UpdateLoyaltyCampaignRequest $request,
        LoyaltyProgram $program,
        LoyaltyCampaign $campaign,
    ): RedirectResponse {
        $this->authorize('manageCampaigns', LoyaltyProgram::class);

        $this->campaigns->update($program, $campaign, UpdateLoyaltyCampaignData::fromRequest($request));

        return $this->backToTab($program, 'campaigns', __('Campaign updated.'));
    }

    public function destroyCampaign(LoyaltyProgram $program, LoyaltyCampaign $campaign): RedirectResponse
    {
        $this->authorize('manageCampaigns', LoyaltyProgram::class);

        $this->campaigns->delete($program, $campaign);

        return $this->backToTab($program, 'campaigns', __('Campaign deleted.'));
    }

    private function backToTab(LoyaltyProgram $program, string $tab, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.loyalty.programs.show', ['program' => $program, 'tab' => $tab])
            ->with('success', $message);
    }
}
