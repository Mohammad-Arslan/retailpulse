<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyTransactionType;
use App\Models\CustomerLoyaltyEvent;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LoyaltyReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function pointsEarned(?int $branchId, array $filters): Collection
    {
        $query = CustomerLoyaltyTransaction::query()
            ->where('transaction_type', LoyaltyTransactionType::Earn)
            ->where('status', 'completed')
            ->select('branch_id', DB::raw('SUM(points) as total_points'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('branch_id');

        $this->applyDateFilters($query, $filters);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function pointsRedeemed(?int $branchId, array $filters): Collection
    {
        $query = CustomerLoyaltyTransaction::query()
            ->where('transaction_type', LoyaltyTransactionType::Redeem)
            ->where('status', 'completed')
            ->select('branch_id', DB::raw('SUM(ABS(points)) as total_points'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('branch_id');

        $this->applyDateFilters($query, $filters);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function pointsExpired(?int $branchId, array $filters): Collection
    {
        $query = CustomerLoyaltyTransaction::query()
            ->where('transaction_type', LoyaltyTransactionType::Expire)
            ->where('status', 'completed')
            ->select('branch_id', DB::raw('SUM(ABS(points)) as total_points'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('branch_id');

        $this->applyDateFilters($query, $filters);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function customerLoyalty(?int $programId, array $filters): Collection
    {
        $query = CustomerLoyaltyWallet::query()
            ->with(['customer:id,name,phone', 'tier:id,name'])
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->orderByDesc('available_points');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->whereHas('customer', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        return $query->limit(100)->get();
    }

    public function tierDistribution(?int $programId): Collection
    {
        return CustomerLoyaltyWallet::query()
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->select('tier_id', DB::raw('COUNT(*) as customer_count'), DB::raw('SUM(available_points) as total_points'))
            ->groupBy('tier_id')
            ->with('tier:id,name,tier_level')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function branchLoyalty(array $filters): Collection
    {
        return CustomerLoyaltyWallet::query()
            ->select(
                'branch_id',
                DB::raw('COUNT(DISTINCT customer_id) as customer_count'),
                DB::raw('SUM(available_points) as available_points'),
                DB::raw('SUM(lifetime_earned_points) as lifetime_earned'),
                DB::raw('SUM(redeemed_points) as redeemed_points'),
            )
            ->groupBy('branch_id')
            ->with('branch:id,name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function campaignEffectiveness(?int $programId, array $filters): Collection
    {
        return LoyaltyCampaign::query()
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->with('program:id,name')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (LoyaltyCampaign $campaign) => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'campaign_type' => $campaign->campaign_type->value,
                'status' => $campaign->status->value,
                'starts_at' => $campaign->starts_at?->toIso8601String(),
                'ends_at' => $campaign->ends_at?->toIso8601String(),
                'bonus_events' => CustomerLoyaltyEvent::query()
                    ->where('program_id', $campaign->program_id)
                    ->where('event_type', 'bonus')
                    ->when($campaign->starts_at, fn ($q) => $q->where('created_at', '>=', $campaign->starts_at))
                    ->when($campaign->ends_at, fn ($q) => $q->where('created_at', '<=', $campaign->ends_at))
                    ->count(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function topCustomers(?int $programId, array $filters, int $limit = 20): Collection
    {
        return CustomerLoyaltyWallet::query()
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->with(['customer:id,name,phone', 'tier:id,name'])
            ->orderByDesc('lifetime_earned_points')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Builder<CustomerLoyaltyTransaction>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateFilters($query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }
    }
}
