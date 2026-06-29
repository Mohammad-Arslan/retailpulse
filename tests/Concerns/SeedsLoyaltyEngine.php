<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\LoyaltyProgramStatus;
use App\Enums\LoyaltyRuleType;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRule;
use App\Models\SystemSetting;
use Database\Seeders\LoyaltyEngineSeeder;

trait SeedsLoyaltyEngine
{
    protected function seedLoyaltyEngine(): LoyaltyProgram
    {
        SystemSetting::set('loyalty', 'enabled', true);
        SystemSetting::set('loyalty', 'workflow_enabled', false);

        $this->seed(LoyaltyEngineSeeder::class);

        return LoyaltyProgram::query()
            ->where('status', LoyaltyProgramStatus::Active)
            ->firstOrFail();
    }

    protected function addSpendRule(LoyaltyProgram $program, int $spendAmount = 100, int $points = 1): LoyaltyRule
    {
        return LoyaltyRule::query()->create([
            'program_id' => $program->id,
            'name' => 'Test Spend Rule',
            'rule_type' => LoyaltyRuleType::SpendBased,
            'priority' => 1,
            'conditions_json' => ['spend_amount' => $spendAmount],
            'reward_json' => ['points' => $points],
            'is_active' => true,
        ]);
    }
}
