<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\DTOs\Loyalty\CreateLoyaltyProgramTierData;
use App\DTOs\Loyalty\UpdateLoyaltyProgramTierData;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramTier;
use App\Services\Loyalty\Concerns\AssertsLoyaltyProgramOwnership;
use Illuminate\Support\Facades\DB;

final class LoyaltyProgramTierService
{
    use AssertsLoyaltyProgramOwnership;

    public function create(LoyaltyProgram $program, CreateLoyaltyProgramTierData $data): LoyaltyProgramTier
    {
        return DB::transaction(fn () => $program->tiers()->create([
            'name' => $data->name,
            'tier_level' => $data->tierLevel,
            'qualification_type' => $data->qualificationType,
            'qualification_value' => $data->qualificationValue,
            'multiplier' => $data->multiplier,
            'benefits_json' => $data->benefitsJson ?? [],
            'status' => $data->status,
        ]));
    }

    public function update(LoyaltyProgram $program, LoyaltyProgramTier $tier, UpdateLoyaltyProgramTierData $data): LoyaltyProgramTier
    {
        $this->assertBelongsToProgram($tier->program_id, $program);

        return DB::transaction(function () use ($tier, $data) {
            $tier->update([
                'name' => $data->name,
                'tier_level' => $data->tierLevel,
                'qualification_type' => $data->qualificationType,
                'qualification_value' => $data->qualificationValue,
                'multiplier' => $data->multiplier,
                'benefits_json' => $data->benefitsJson ?? [],
                'status' => $data->status,
            ]);

            return $tier;
        });
    }

    public function delete(LoyaltyProgram $program, LoyaltyProgramTier $tier): void
    {
        $this->assertBelongsToProgram($tier->program_id, $program);

        DB::transaction(fn () => $tier->delete());
    }
}
