<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyCampaignType;
use App\Enums\LoyaltyRuleType;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRule;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Collection;

final class LoyaltyRuleEngine
{
    /**
     * @return array{points: int, breakdown: list<array{rule_id: int, rule_name: string, points: int, multiplier: float}>}
     */
    public function evaluateForSale(Sale $sale, LoyaltyProgram $program, ?float $tierMultiplier = 1.0): array
    {
        $sale->loadMissing(['items.product', 'customer']);

        if ($sale->customer_id === null) {
            return ['points' => 0, 'breakdown' => []];
        }

        $rules = $this->activeRules($program);
        $basePoints = 0;
        $multiplier = max(1.0, (float) $tierMultiplier);
        $bonuses = 0;
        $breakdown = [];

        foreach ($rules as $rule) {
            if ($rule->rule_type === LoyaltyRuleType::Redemption) {
                continue;
            }

            $result = $this->evaluateRule($rule, $sale, $program);

            if ($result === null) {
                continue;
            }

            if (($result['type'] ?? 'points') === 'multiplier') {
                $multiplier *= (float) ($result['value'] ?? 1);
                $breakdown[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'points' => 0,
                    'multiplier' => (float) ($result['value'] ?? 1),
                ];
            } elseif (($result['type'] ?? 'points') === 'bonus') {
                $bonus = (int) ($result['value'] ?? 0);
                $bonuses += $bonus;
                $breakdown[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'points' => $bonus,
                    'multiplier' => 1.0,
                ];
            } else {
                $earned = (int) ($result['value'] ?? 0);
                $basePoints += $earned;
                $breakdown[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'points' => $earned,
                    'multiplier' => 1.0,
                ];
            }
        }

        $campaignMultiplier = $this->activeCampaignMultiplier($program);
        $multiplier *= $campaignMultiplier;

        $scaledBase = (int) floor($basePoints * $multiplier);
        $total = $scaledBase + $bonuses;

        if ($campaignMultiplier > 1 && $basePoints > 0) {
            $breakdown[] = [
                'rule_id' => 0,
                'rule_name' => __('Active Campaign Multiplier'),
                'points' => $scaledBase - $basePoints,
                'multiplier' => $campaignMultiplier,
            ];
        }

        return [
            'points' => max(0, $total),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return Collection<int, LoyaltyRule>
     */
    private function activeRules(LoyaltyProgram $program): Collection
    {
        return $program->rules()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (LoyaltyRule $rule) => $rule->isEffectiveNow());
    }

    /**
     * @return array{type: string, value: float|int}|null
     */
    private function evaluateRule(LoyaltyRule $rule, Sale $sale, LoyaltyProgram $program): ?array
    {
        $conditions = $rule->conditions_json ?? [];
        $reward = $rule->reward_json ?? [];

        return match ($rule->rule_type) {
            LoyaltyRuleType::SpendBased => $this->evaluateSpendBased($sale, $conditions, $reward),
            LoyaltyRuleType::ProductBased => $this->evaluateProductBased($sale, $conditions, $reward),
            LoyaltyRuleType::CategoryBased => $this->evaluateCategoryBased($sale, $conditions, $reward),
            LoyaltyRuleType::BranchBased => $this->evaluateBranchBased($sale, $conditions, $reward),
            LoyaltyRuleType::TimeBased => $this->evaluateTimeBased($conditions, $reward),
            LoyaltyRuleType::Birthday => $this->evaluateBirthday($sale->customer, $conditions, $reward),
            LoyaltyRuleType::FirstPurchase => $this->evaluateFirstPurchase($sale, $conditions, $reward),
            LoyaltyRuleType::Campaign => $this->evaluateCampaignRule($conditions, $reward),
            LoyaltyRuleType::ManualBonus => null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     * @return array{type: string, value: int}|null
     */
    private function evaluateSpendBased(Sale $sale, array $conditions, array $reward): ?array
    {
        $spendAmount = (float) ($conditions['spend_amount'] ?? 100);
        $pointsPerUnit = (int) ($reward['points'] ?? 1);

        if ($spendAmount <= 0 || $pointsPerUnit <= 0) {
            return null;
        }

        $eligibleTotal = (float) $sale->grand_total;
        $points = (int) floor($eligibleTotal / $spendAmount) * $pointsPerUnit;

        return $points > 0 ? ['type' => 'points', 'value' => $points] : null;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     * @return array{type: string, value: int}|null
     */
    private function evaluateProductBased(Sale $sale, array $conditions, array $reward): ?array
    {
        $productIds = array_map('intval', (array) ($conditions['product_ids'] ?? []));
        $pointsPerMatch = (int) ($reward['points'] ?? 0);

        if ($productIds === [] || $pointsPerMatch <= 0) {
            return null;
        }

        $matches = $sale->items->filter(fn ($item) => in_array((int) $item->product_id, $productIds, true));

        if ($matches->isEmpty()) {
            return null;
        }

        $qty = (int) $matches->sum('quantity');

        return ['type' => 'points', 'value' => $qty * $pointsPerMatch];
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     * @return array{type: string, value: float|int}|null
     */
    private function evaluateCategoryBased(Sale $sale, array $conditions, array $reward): ?array
    {
        $categoryIds = array_map('intval', (array) ($conditions['category_ids'] ?? []));

        if ($categoryIds === []) {
            return null;
        }

        $hasMatch = $sale->items->contains(function ($item) use ($categoryIds) {
            $product = $item->relationLoaded('product') ? $item->product : Product::query()->find($item->product_id);

            return $product !== null && in_array((int) $product->category_id, $categoryIds, true);
        });

        if (! $hasMatch) {
            return null;
        }

        if (isset($reward['multiplier'])) {
            return ['type' => 'multiplier', 'value' => (float) $reward['multiplier']];
        }

        $points = (int) ($reward['points'] ?? 0);

        return $points > 0 ? ['type' => 'points', 'value' => $points] : null;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     * @return array{type: string, value: float}|null
     */
    private function evaluateBranchBased(Sale $sale, array $conditions, array $reward): ?array
    {
        $branchIds = array_map('intval', (array) ($conditions['branch_ids'] ?? []));

        if ($branchIds === [] || ! in_array((int) $sale->branch_id, $branchIds, true)) {
            return null;
        }

        if (isset($reward['multiplier'])) {
            return ['type' => 'multiplier', 'value' => (float) $reward['multiplier']];
        }

        $points = (int) ($reward['points'] ?? 0);

        return $points > 0 ? ['type' => 'points', 'value' => $points] : null;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     * @return array{type: string, value: float|int}|null
     */
    private function evaluateTimeBased(array $conditions, array $reward): ?array
    {
        $daysOfWeek = array_map('intval', (array) ($conditions['day_of_week'] ?? []));
        $currentDay = (int) now()->dayOfWeek;

        if ($daysOfWeek !== [] && ! in_array($currentDay, $daysOfWeek, true)) {
            return null;
        }

        if (isset($reward['multiplier'])) {
            return ['type' => 'multiplier', 'value' => (float) $reward['multiplier']];
        }

        $points = (int) ($reward['points'] ?? 0);

        return $points > 0 ? ['type' => 'points', 'value' => $points] : null;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     * @return array{type: string, value: int}|null
     */
    private function evaluateBirthday(?Customer $customer, array $conditions, array $reward): ?array
    {
        if ($customer === null) {
            return null;
        }

        $currentMonth = (int) now()->month;
        $birthdayMonth = (int) ($conditions['birthday_month'] ?? 0);

        if ($birthdayMonth > 0 && $birthdayMonth !== $currentMonth) {
            return null;
        }

        if ($birthdayMonth === 0 && ! (bool) ($conditions['match_current_month'] ?? false)) {
            return null;
        }

        if ($birthdayMonth === 0 && (bool) ($conditions['match_current_month'] ?? false)) {
            $customerMonth = (int) ($customer->created_at?->month ?? 0);
            if ($customerMonth !== $currentMonth) {
                return null;
            }
        }

        $bonus = (int) ($reward['bonus_points'] ?? $conditions['bonus_points'] ?? 0);

        return $bonus > 0 ? ['type' => 'bonus', 'value' => $bonus] : null;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     * @return array{type: string, value: int}|null
     */
    private function evaluateFirstPurchase(Sale $sale, array $conditions, array $reward): ?array
    {
        $priorSales = Sale::query()
            ->where('customer_id', $sale->customer_id)
            ->where('status', SaleStatus::Completed)
            ->where('id', '!=', $sale->id)
            ->exists();

        if ($priorSales) {
            return null;
        }

        $bonus = (int) ($reward['bonus_points'] ?? $conditions['bonus_points'] ?? 0);

        return $bonus > 0 ? ['type' => 'bonus', 'value' => $bonus] : null;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $reward
     * @return array{type: string, value: int}|null
     */
    private function evaluateCampaignRule(array $conditions, array $reward): ?array
    {
        $bonus = (int) ($reward['bonus_points'] ?? $conditions['bonus_points'] ?? 0);

        return $bonus > 0 ? ['type' => 'bonus', 'value' => $bonus] : null;
    }

    private function activeCampaignMultiplier(LoyaltyProgram $program): float
    {
        $multiplier = 1.0;

        $campaigns = $program->campaigns()
            ->where('status', 'active')
            ->get()
            ->filter(fn (LoyaltyCampaign $campaign) => $campaign->isActiveNow());

        foreach ($campaigns as $campaign) {
            $config = $campaign->configuration_json ?? [];

            $multiplier *= match ($campaign->campaign_type) {
                LoyaltyCampaignType::DoublePoints => 2.0,
                LoyaltyCampaignType::Multiplier => (float) ($config['multiplier'] ?? 1),
                LoyaltyCampaignType::BonusPoints => 1.0,
            };
        }

        return max(1.0, $multiplier);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveRedemptionConfig(LoyaltyProgram $program): ?array
    {
        $rule = $program->rules()
            ->where('rule_type', LoyaltyRuleType::Redemption)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();

        if ($rule === null || ! $rule->isEffectiveNow()) {
            return null;
        }

        return array_merge($rule->conditions_json ?? [], $rule->reward_json ?? []);
    }
}
