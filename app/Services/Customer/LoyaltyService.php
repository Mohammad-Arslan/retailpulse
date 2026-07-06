<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\LoyaltyPointType;
use App\Models\Customer;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTier;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Services\Loyalty\LoyaltyEarnService;
use Illuminate\Support\Facades\DB;

final class LoyaltyService
{
    public function __construct(
        private readonly LoyaltyEarnService $loyaltyEngine,
    ) {}

    public function earnOnSaleComplete(Sale $sale): ?LoyaltyPoint
    {
        if ((bool) SystemSetting::get('loyalty', 'enabled', true)) {
            $this->loyaltyEngine->earnOnSaleComplete($sale);

            return null;
        }

        return $this->earnLegacyOnSaleComplete($sale);
    }

    private function earnLegacyOnSaleComplete(Sale $sale): ?LoyaltyPoint
    {
        if ($sale->customer_id === null) {
            return null;
        }

        $sale->loadMissing('customer.loyaltyTier');

        $pointsPer100 = (int) SystemSetting::get('customers', 'loyalty_points_per_100', 1);

        if ($pointsPer100 <= 0) {
            return null;
        }

        $basePoints = (int) floor((float) $sale->grand_total / 100) * $pointsPer100;

        if ($basePoints <= 0) {
            return null;
        }

        $multiplier = (float) ($sale->customer?->loyaltyTier?->points_multiplier ?? 1);
        $points = (int) floor($basePoints * $multiplier);

        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($sale, $points) {
            $entry = LoyaltyPoint::query()->create([
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'points' => $points,
                'type' => LoyaltyPointType::Earn,
                'description' => __('Points earned on sale #:id', ['id' => $sale->id]),
                'created_at' => now(),
            ]);

            $this->recalculateTier($sale->customer);

            return $entry;
        });
    }

    public function getTotalPoints(int $customerId): int
    {
        if ((bool) SystemSetting::get('loyalty', 'enabled', true)) {
            return (int) CustomerLoyaltyWallet::query()
                ->where('customer_id', $customerId)
                ->sum('available_points');
        }

        return (int) LoyaltyPoint::query()
            ->where('customer_id', $customerId)
            ->sum('points');
    }

    public function recalculateTier(Customer $customer): Customer
    {
        if (! SystemSetting::get('customers', 'loyalty_auto_tier', true)) {
            return $customer;
        }

        $totalPoints = $this->getTotalPoints($customer->id);

        $tier = LoyaltyTier::query()
            ->where('is_active', true)
            ->where('auto_upgrade', true)
            ->where('min_points', '<=', $totalPoints)
            ->orderByDesc('min_points')
            ->orderByDesc('sort_order')
            ->first();

        if ($tier !== null && $customer->loyalty_tier_id !== $tier->id) {
            $customer->update(['loyalty_tier_id' => $tier->id]);
            $customer->load('loyaltyTier');
        }

        return $customer;
    }
}
