<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\DTOs\Loyalty\CreateLoyaltyRuleData;
use App\DTOs\Loyalty\UpdateLoyaltyRuleData;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRule;
use App\Services\Loyalty\Concerns\AssertsLoyaltyProgramOwnership;
use Illuminate\Support\Facades\DB;

final class LoyaltyRuleService
{
    use AssertsLoyaltyProgramOwnership;

    public function create(LoyaltyProgram $program, CreateLoyaltyRuleData $data): LoyaltyRule
    {
        return DB::transaction(fn () => $program->rules()->create([
            'name' => $data->name,
            'description' => $data->description,
            'rule_type' => $data->ruleType,
            'priority' => $data->priority,
            'conditions_json' => $data->conditionsJson ?? [],
            'reward_json' => $data->rewardJson ?? [],
            'is_active' => $data->isActive,
            'effective_from' => $data->effectiveFrom,
            'effective_to' => $data->effectiveTo,
        ]));
    }

    public function update(LoyaltyProgram $program, LoyaltyRule $rule, UpdateLoyaltyRuleData $data): LoyaltyRule
    {
        $this->assertBelongsToProgram($rule->program_id, $program);

        return DB::transaction(function () use ($rule, $data) {
            $rule->update([
                'name' => $data->name,
                'description' => $data->description,
                'rule_type' => $data->ruleType,
                'priority' => $data->priority,
                'conditions_json' => $data->conditionsJson ?? [],
                'reward_json' => $data->rewardJson ?? [],
                'is_active' => $data->isActive,
                'effective_from' => $data->effectiveFrom,
                'effective_to' => $data->effectiveTo,
            ]);

            return $rule;
        });
    }

    public function delete(LoyaltyProgram $program, LoyaltyRule $rule): void
    {
        $this->assertBelongsToProgram($rule->program_id, $program);

        DB::transaction(fn () => $rule->delete());
    }
}
