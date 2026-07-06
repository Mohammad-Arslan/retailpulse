<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyApprovalMode;
use App\Enums\LoyaltyApprovalThresholdType;
use App\Enums\LoyaltyCampaignStatus;
use App\Enums\LoyaltyCampaignType;
use App\Enums\LoyaltyExpiryType;
use App\Enums\LoyaltyProgramScopeType;
use App\Enums\LoyaltyProgramStatus;
use App\Enums\LoyaltyRuleType;
use App\Enums\LoyaltyScopeMode;
use App\Enums\LoyaltyTierQualificationType;
use App\Models\LoyaltyApprovalPolicy;
use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyExpiryRule;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramTier;
use App\Models\LoyaltyRule;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

final class LoyaltyEngineSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->first();

        $program = LoyaltyProgram::query()->firstOrCreate(
            ['name' => 'RetailPulse Rewards'],
            [
                'description' => 'Default configurable loyalty program',
                'scope_type' => LoyaltyProgramScopeType::Global,
                'earn_scope' => LoyaltyScopeMode::Global,
                'redeem_scope' => LoyaltyScopeMode::Global,
                'allow_cross_branch_earn' => true,
                'allow_cross_branch_redeem' => true,
                'status' => LoyaltyProgramStatus::Active,
                'starts_at' => now()->subDay(),
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ],
        );

        LoyaltyRule::query()->firstOrCreate(
            ['program_id' => $program->id, 'name' => 'Spend 100 = 1 Point', 'rule_type' => LoyaltyRuleType::SpendBased],
            [
                'description' => 'Earn 1 point per 100 currency spent',
                'priority' => 10,
                'conditions_json' => ['spend_amount' => 100],
                'reward_json' => ['points' => 1],
                'is_active' => true,
            ],
        );

        LoyaltyRule::query()->firstOrCreate(
            ['program_id' => $program->id, 'name' => 'First Purchase Bonus', 'rule_type' => LoyaltyRuleType::FirstPurchase],
            [
                'priority' => 20,
                'conditions_json' => [],
                'reward_json' => ['bonus_points' => 100],
                'is_active' => true,
            ],
        );

        LoyaltyRule::query()->firstOrCreate(
            ['program_id' => $program->id, 'name' => 'Standard Redemption', 'rule_type' => LoyaltyRuleType::Redemption],
            [
                'priority' => 100,
                'conditions_json' => [
                    'min_redeem_points' => 100,
                    'max_redeem_percent' => 50,
                    'points_per_unit' => 100,
                    'currency_per_unit' => 100,
                ],
                'reward_json' => [],
                'is_active' => true,
            ],
        );

        foreach ([
            ['name' => 'Silver', 'tier_level' => 1, 'qualification_type' => LoyaltyTierQualificationType::PointsBased, 'qualification_value' => 0, 'multiplier' => 1],
            ['name' => 'Gold', 'tier_level' => 2, 'qualification_type' => LoyaltyTierQualificationType::PointsBased, 'qualification_value' => 5000, 'multiplier' => 1.25],
            ['name' => 'Platinum', 'tier_level' => 3, 'qualification_type' => LoyaltyTierQualificationType::SpendBased, 'qualification_value' => 50000, 'multiplier' => 1.5],
            ['name' => 'VIP', 'tier_level' => 4, 'qualification_type' => LoyaltyTierQualificationType::VisitBased, 'qualification_value' => 50, 'multiplier' => 2],
        ] as $tier) {
            LoyaltyProgramTier::query()->firstOrCreate(
                ['program_id' => $program->id, 'tier_level' => $tier['tier_level']],
                [
                    'name' => $tier['name'],
                    'qualification_type' => $tier['qualification_type'],
                    'qualification_value' => $tier['qualification_value'],
                    'multiplier' => $tier['multiplier'],
                    'benefits_json' => ['multiplier' => $tier['multiplier']],
                    'status' => 'active',
                ],
            );
        }

        LoyaltyApprovalPolicy::query()->firstOrCreate(
            ['program_id' => $program->id, 'action_type' => LoyaltyApprovalActionType::ManualAdjustment],
            [
                'threshold_type' => LoyaltyApprovalThresholdType::Points,
                'threshold_value' => 1000,
                'approval_mode' => LoyaltyApprovalMode::Pin,
                'is_active' => true,
            ],
        );

        LoyaltyApprovalPolicy::query()->firstOrCreate(
            ['program_id' => $program->id, 'action_type' => LoyaltyApprovalActionType::LargeRedemption],
            [
                'threshold_type' => LoyaltyApprovalThresholdType::Points,
                'threshold_value' => 5000,
                'approval_mode' => LoyaltyApprovalMode::Pin,
                'is_active' => true,
            ],
        );

        LoyaltyApprovalPolicy::query()->firstOrCreate(
            ['program_id' => $program->id, 'action_type' => LoyaltyApprovalActionType::BonusPoints],
            [
                'threshold_type' => LoyaltyApprovalThresholdType::Points,
                'threshold_value' => 1000,
                'approval_mode' => LoyaltyApprovalMode::Pin,
                'is_active' => true,
            ],
        );

        LoyaltyExpiryRule::query()->firstOrCreate(
            ['program_id' => $program->id],
            [
                'expiry_type' => LoyaltyExpiryType::FixedMonths,
                'value' => 12,
                'grace_period_days' => 30,
            ],
        );

        LoyaltyCampaign::query()->firstOrCreate(
            ['program_id' => $program->id, 'name' => 'Friday Double Points'],
            [
                'description' => 'Earn double points every Friday',
                'campaign_type' => LoyaltyCampaignType::DoublePoints,
                'configuration_json' => ['day_of_week' => [5]],
                'starts_at' => now()->subMonth(),
                'status' => LoyaltyCampaignStatus::Active,
            ],
        );

        SystemSetting::set('loyalty', 'enabled', true);
        SystemSetting::set('loyalty', 'workflow_enabled', false);
    }
}
