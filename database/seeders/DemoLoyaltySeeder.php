<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramTier;
use Illuminate\Database\Seeder;

final class DemoLoyaltySeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LoyaltyEngineSeeder::class);

        $program = LoyaltyProgram::query()->where('name', 'RetailPulse Rewards')->first();

        if ($program === null) {
            return;
        }

        $branchIds = Branch::query()->where('is_active', true)->pluck('id');
        $program->branches()->syncWithoutDetaching($branchIds);

        $silverTier = LoyaltyProgramTier::query()
            ->where('program_id', $program->id)
            ->where('name', 'Silver')
            ->first();

        $goldTier = LoyaltyProgramTier::query()
            ->where('program_id', $program->id)
            ->where('name', 'Gold')
            ->first();

        $pointsByPhone = [
            '+15550100001' => ['points' => 1250, 'tier' => $goldTier],
            '+15550100003' => ['points' => 480, 'tier' => $silverTier],
            '+15550100004' => ['points' => 90, 'tier' => $silverTier],
        ];

        foreach ($pointsByPhone as $phone => $walletData) {
            $customer = Customer::query()->where('phone', $phone)->first();

            if ($customer === null) {
                continue;
            }

            CustomerLoyaltyWallet::query()->firstOrCreate(
                [
                    'customer_id' => $customer->id,
                    'program_id' => $program->id,
                ],
                [
                    'tier_id' => $walletData['tier']?->id,
                    'available_points' => $walletData['points'],
                    'lifetime_earned_points' => $walletData['points'],
                ],
            );
        }
    }
}
