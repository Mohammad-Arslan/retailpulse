<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LoyaltyProgramScopeType;
use App\Enums\LoyaltyProgramStatus;
use App\Enums\LoyaltyScopeMode;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyProgram>
 */
final class LoyaltyProgramFactory extends Factory
{
    protected $model = LoyaltyProgram::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Rewards',
            'description' => fake()->sentence(),
            'scope_type' => LoyaltyProgramScopeType::Global,
            'earn_scope' => LoyaltyScopeMode::Global,
            'redeem_scope' => LoyaltyScopeMode::Global,
            'allow_cross_branch_earn' => true,
            'allow_cross_branch_redeem' => true,
            'status' => LoyaltyProgramStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ];
    }
}
