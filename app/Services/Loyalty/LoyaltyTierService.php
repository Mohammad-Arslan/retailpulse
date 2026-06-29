<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyEventType;
use App\Enums\LoyaltyTierQualificationType;
use App\Enums\SaleStatus;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramTier;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

final class LoyaltyTierService
{
    public function __construct(
        private readonly LoyaltyTimelineService $timeline,
    ) {}

    public function recalculateForWallet(CustomerLoyaltyWallet $wallet): CustomerLoyaltyWallet
    {
        $program = $wallet->program ?? LoyaltyProgram::query()->findOrFail($wallet->program_id);

        $tiers = $program->tiers()
            ->where('status', 'active')
            ->orderByDesc('tier_level')
            ->get();

        if ($tiers->isEmpty()) {
            return $wallet;
        }

        $qualified = $tiers->first(fn (LoyaltyProgramTier $tier) => $this->meetsQualification($wallet, $tier));

        if ($qualified === null) {
            return $wallet;
        }

        if ($wallet->tier_id === $qualified->id) {
            return $wallet;
        }

        return DB::transaction(function () use ($wallet, $qualified, $program) {
            $previousTierId = $wallet->tier_id;
            $beforeTier = $wallet->tier?->name;
            $wallet->update(['tier_id' => $qualified->id]);
            $wallet->load('tier');

            $this->timeline->record(
                $wallet->customer_id,
                $program,
                LoyaltyEventType::TierChange,
                0,
                (int) $wallet->available_points,
                (int) $wallet->available_points,
                __('Tier changed from :from to :to', [
                    'from' => $beforeTier ?? __('None'),
                    'to' => $qualified->name,
                ]),
                [
                    'previous_tier_id' => $previousTierId,
                    'new_tier_id' => $qualified->id,
                ],
            );

            return $wallet->fresh(['tier']);
        });
    }

    public function getTierMultiplier(CustomerLoyaltyWallet $wallet): float
    {
        $wallet->loadMissing('tier');

        return (float) ($wallet->tier?->multiplier ?? 1.0);
    }

    private function meetsQualification(CustomerLoyaltyWallet $wallet, LoyaltyProgramTier $tier): bool
    {
        $required = (float) $tier->qualification_value;

        return match ($tier->qualification_type) {
            LoyaltyTierQualificationType::PointsBased => (float) $wallet->lifetime_earned_points >= $required,
            LoyaltyTierQualificationType::SpendBased => $this->customerSpend($wallet->customer_id) >= $required,
            LoyaltyTierQualificationType::VisitBased => $this->customerVisits($wallet->customer_id) >= (int) $required,
        };
    }

    private function customerSpend(int $customerId): float
    {
        return (float) Sale::query()
            ->where('customer_id', $customerId)
            ->where('status', SaleStatus::Completed)
            ->sum('grand_total');
    }

    private function customerVisits(int $customerId): int
    {
        return Sale::query()
            ->where('customer_id', $customerId)
            ->where('status', SaleStatus::Completed)
            ->count();
    }
}
