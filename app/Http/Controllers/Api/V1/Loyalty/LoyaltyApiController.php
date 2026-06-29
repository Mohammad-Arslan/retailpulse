<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLoyaltyEvent;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyCampaign;
use App\Services\Loyalty\LoyaltyProgramService;
use App\Services\Loyalty\LoyaltyRedemptionService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoyaltyApiController extends Controller
{
    public function __construct(
        private readonly LoyaltyProgramService $programs,
        private readonly LoyaltyRedemptionService $redemption,
    ) {}

    public function wallet(Request $request, Customer $customer): JsonResponse
    {
        $branchId = app(BranchContext::class)->branchId ?? $request->integer('branch_id');
        $program = $this->programs->resolveActiveProgramForBranch((int) $branchId);

        if ($program === null) {
            return response()->json(['wallet' => null]);
        }

        $wallet = CustomerLoyaltyWallet::query()
            ->where('customer_id', $customer->id)
            ->where('program_id', $program->id)
            ->with('tier:id,name,multiplier')
            ->first();

        return response()->json([
            'program' => ['id' => $program->id, 'name' => $program->name],
            'wallet' => $wallet ? [
                'available_points' => $wallet->available_points,
                'pending_points' => $wallet->pending_points,
                'redeemed_points' => $wallet->redeemed_points,
                'expired_points' => $wallet->expired_points,
                'lifetime_earned_points' => $wallet->lifetime_earned_points,
                'tier' => $wallet->tier ? [
                    'name' => $wallet->tier->name,
                    'multiplier' => (float) $wallet->tier->multiplier,
                ] : null,
            ] : null,
        ]);
    }

    public function transactions(Request $request, Customer $customer): JsonResponse
    {
        $programId = $request->integer('program_id');

        $query = CustomerLoyaltyTransaction::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(50);

        if ($programId > 0) {
            $query->where('program_id', $programId);
        }

        return response()->json([
            'transactions' => $query->get()->map(fn (CustomerLoyaltyTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->transaction_type->value,
                'points' => $tx->points,
                'status' => $tx->status->value,
                'reason' => $tx->reason,
                'created_at' => $tx->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function timeline(Request $request, Customer $customer): JsonResponse
    {
        $programId = $request->integer('program_id');

        $query = CustomerLoyaltyEvent::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(50);

        if ($programId > 0) {
            $query->where('program_id', $programId);
        }

        return response()->json([
            'events' => $query->get()->map(fn (CustomerLoyaltyEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type->value,
                'points' => $event->points,
                'before_balance' => $event->before_balance,
                'after_balance' => $event->after_balance,
                'description' => $event->description,
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function tierStatus(Request $request, Customer $customer): JsonResponse
    {
        $branchId = app(BranchContext::class)->branchId ?? $request->integer('branch_id');
        $program = $this->programs->resolveActiveProgramForBranch((int) $branchId);

        if ($program === null) {
            return response()->json(['tier' => null]);
        }

        $wallet = CustomerLoyaltyWallet::query()
            ->where('customer_id', $customer->id)
            ->where('program_id', $program->id)
            ->with('tier')
            ->first();

        $nextTier = $program->tiers()
            ->where('status', 'active')
            ->when($wallet?->tier, fn ($q) => $q->where('tier_level', '>', $wallet->tier->tier_level))
            ->orderBy('tier_level')
            ->first();

        return response()->json([
            'current_tier' => $wallet?->tier ? [
                'name' => $wallet->tier->name,
                'level' => $wallet->tier->tier_level,
                'multiplier' => (float) $wallet->tier->multiplier,
            ] : null,
            'next_tier' => $nextTier ? [
                'name' => $nextTier->name,
                'qualification_type' => $nextTier->qualification_type->value,
                'qualification_value' => (float) $nextTier->qualification_value,
            ] : null,
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->branchId ?? $request->integer('branch_id');
        $program = $this->programs->resolveActiveProgramForBranch((int) $branchId);

        if ($program === null) {
            return response()->json(['campaigns' => []]);
        }

        $campaigns = LoyaltyCampaign::query()
            ->where('program_id', $program->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (LoyaltyCampaign $c) => $c->isActiveNow());

        return response()->json([
            'campaigns' => $campaigns->map(fn (LoyaltyCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'campaign_type' => $c->campaign_type->value,
                'configuration' => $c->configuration_json,
                'starts_at' => $c->starts_at?->toIso8601String(),
                'ends_at' => $c->ends_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function redemptionOptions(Request $request, Customer $customer): JsonResponse
    {
        $branchId = app(BranchContext::class)->branchId ?? $request->integer('branch_id');
        $program = $this->programs->resolveActiveProgramForBranch((int) $branchId);

        if ($program === null) {
            return response()->json(['options' => null]);
        }

        $wallet = CustomerLoyaltyWallet::query()
            ->where('customer_id', $customer->id)
            ->where('program_id', $program->id)
            ->first();

        if ($wallet === null) {
            $wallet = new CustomerLoyaltyWallet([
                'customer_id' => $customer->id,
                'program_id' => $program->id,
                'available_points' => 0,
            ]);
        }

        return response()->json([
            'options' => $this->redemption->redemptionOptions($wallet, $program),
        ]);
    }
}
