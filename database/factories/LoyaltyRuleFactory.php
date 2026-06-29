<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LoyaltyRuleType;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyRule>
 */
final class LoyaltyRuleFactory extends Factory
{
    protected $model = LoyaltyRule::class;

    public function definition(): array
    {
        return [
            'program_id' => LoyaltyProgram::factory(),
            'name' => 'Spend Rule',
            'rule_type' => LoyaltyRuleType::SpendBased,
            'priority' => 10,
            'conditions_json' => ['spend_amount' => 100],
            'reward_json' => ['points' => 1],
            'is_active' => true,
        ];
    }

    public function spendBased(int $spendAmount = 100, int $points = 1): static
    {
        return $this->state(fn () => [
            'rule_type' => LoyaltyRuleType::SpendBased,
            'conditions_json' => ['spend_amount' => $spendAmount],
            'reward_json' => ['points' => $points],
        ]);
    }
}
