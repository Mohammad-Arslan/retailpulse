<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Models\Customer;
use App\Models\CustomerLoyaltyEvent;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\CustomerLoyaltyWallet;

final class LoyaltyProfileService
{
    public function __construct(
        private readonly LoyaltyProgramService $programs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildLoyaltySummary(Customer $customer, ?int $branchId = null): array
    {
        if ($branchId === null) {
            $wallets = CustomerLoyaltyWallet::query()
                ->where('customer_id', $customer->id)
                ->with(['program:id,name', 'tier:id,name,multiplier'])
                ->get();
        } else {
            $program = $this->programs->resolveActiveProgramForBranch($branchId);

            if ($program === null) {
                return [
                    'enabled' => false,
                    'wallets' => [],
                    'transactions' => [],
                    'timeline' => [],
                ];
            }

            $wallets = CustomerLoyaltyWallet::query()
                ->where('customer_id', $customer->id)
                ->where('program_id', $program->id)
                ->with(['program:id,name', 'tier:id,name,multiplier'])
                ->get();
        }

        if ($wallets->isEmpty()) {
            return [
                'enabled' => true,
                'wallets' => [],
                'transactions' => [],
                'timeline' => [],
            ];
        }

        $programIds = $wallets->pluck('program_id');

        $transactions = CustomerLoyaltyTransaction::query()
            ->where('customer_id', $customer->id)
            ->whereIn('program_id', $programIds)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (CustomerLoyaltyTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->transaction_type->value,
                'points' => $tx->points,
                'status' => $tx->status->value,
                'reason' => $tx->reason,
                'created_at' => $tx->created_at?->toIso8601String(),
            ]);

        $timeline = CustomerLoyaltyEvent::query()
            ->where('customer_id', $customer->id)
            ->whereIn('program_id', $programIds)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (CustomerLoyaltyEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type->value,
                'points' => $event->points,
                'description' => $event->description,
                'before_balance' => $event->before_balance,
                'after_balance' => $event->after_balance,
                'created_at' => $event->created_at?->toIso8601String(),
            ]);

        return [
            'enabled' => true,
            'wallets' => $wallets->map(fn (CustomerLoyaltyWallet $wallet) => [
                'program' => $wallet->program?->name,
                'available_points' => $wallet->available_points,
                'pending_points' => $wallet->pending_points,
                'redeemed_points' => $wallet->redeemed_points,
                'expired_points' => $wallet->expired_points,
                'lifetime_earned_points' => $wallet->lifetime_earned_points,
                'tier' => $wallet->tier ? [
                    'name' => $wallet->tier->name,
                    'multiplier' => (float) $wallet->tier->multiplier,
                ] : null,
            ])->values()->all(),
            'transactions' => $transactions->values()->all(),
            'timeline' => $timeline->values()->all(),
        ];
    }
}
