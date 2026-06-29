<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\DTOs\Loyalty\CreateLoyaltyExpiryRuleData;
use App\DTOs\Loyalty\UpdateLoyaltyExpiryRuleData;
use App\Models\LoyaltyExpiryRule;
use App\Models\LoyaltyProgram;
use App\Services\Loyalty\Concerns\AssertsLoyaltyProgramOwnership;
use Illuminate\Support\Facades\DB;

final class LoyaltyExpiryRuleService
{
    use AssertsLoyaltyProgramOwnership;

    public function create(LoyaltyProgram $program, CreateLoyaltyExpiryRuleData $data): LoyaltyExpiryRule
    {
        return DB::transaction(fn () => $program->expiryRules()->create([
            'expiry_type' => $data->expiryType,
            'value' => $data->value,
            'grace_period_days' => $data->gracePeriodDays,
        ]));
    }

    public function update(
        LoyaltyProgram $program,
        LoyaltyExpiryRule $expiryRule,
        UpdateLoyaltyExpiryRuleData $data,
    ): LoyaltyExpiryRule {
        $this->assertBelongsToProgram($expiryRule->program_id, $program);

        return DB::transaction(function () use ($expiryRule, $data) {
            $expiryRule->update([
                'expiry_type' => $data->expiryType,
                'value' => $data->value,
                'grace_period_days' => $data->gracePeriodDays,
            ]);

            return $expiryRule;
        });
    }

    public function delete(LoyaltyProgram $program, LoyaltyExpiryRule $expiryRule): void
    {
        $this->assertBelongsToProgram($expiryRule->program_id, $program);

        DB::transaction(fn () => $expiryRule->delete());
    }
}
