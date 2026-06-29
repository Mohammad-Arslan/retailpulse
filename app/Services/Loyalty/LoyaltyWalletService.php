<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyEventType;
use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Facades\DB;

final class LoyaltyWalletService
{
    public function __construct(
        private readonly LoyaltyTimelineService $timeline,
    ) {}

    public function getOrCreateWallet(
        int $customerId,
        LoyaltyProgram $program,
        ?int $branchId = null,
        ?int $tenantId = null,
    ): CustomerLoyaltyWallet {
        return CustomerLoyaltyWallet::query()->firstOrCreate(
            [
                'customer_id' => $customerId,
                'program_id' => $program->id,
                'branch_id' => $branchId,
            ],
            [
                'tenant_id' => $tenantId,
                'available_points' => 0,
                'pending_points' => 0,
                'redeemed_points' => 0,
                'expired_points' => 0,
                'lifetime_earned_points' => 0,
            ],
        );
    }

    /**
     * @return array{transaction: CustomerLoyaltyTransaction, wallet: CustomerLoyaltyWallet}
     */
    public function credit(
        CustomerLoyaltyWallet $wallet,
        int $points,
        LoyaltyTransactionType $type,
        LoyaltyTransactionStatus $status,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $userId = null,
        ?LoyaltyEventType $eventType = null,
    ): array {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Credit points must be positive.');
        }

        return DB::transaction(function () use ($wallet, $points, $type, $status, $referenceType, $referenceId, $reason, $userId, $eventType) {
            $wallet = CustomerLoyaltyWallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $before = (int) $wallet->available_points;

            if ($status === LoyaltyTransactionStatus::PendingApproval) {
                $wallet->update([
                    'pending_points' => $wallet->pending_points + $points,
                ]);
                $after = $before;
            } else {
                $wallet->update([
                    'available_points' => $wallet->available_points + $points,
                    'lifetime_earned_points' => $wallet->lifetime_earned_points + $points,
                ]);
                $after = $before + $points;
            }

            $transaction = CustomerLoyaltyTransaction::query()->create([
                'tenant_id' => $wallet->tenant_id,
                'customer_id' => $wallet->customer_id,
                'program_id' => $wallet->program_id,
                'wallet_id' => $wallet->id,
                'branch_id' => $wallet->branch_id,
                'transaction_type' => $type,
                'points' => $points,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason,
                'status' => $status,
                'created_by' => $userId,
            ]);

            if ($status === LoyaltyTransactionStatus::Completed && $eventType !== null) {
                $wallet->loadMissing('program');
                $this->timeline->record(
                    $wallet->customer_id,
                    $wallet->program,
                    $eventType,
                    $points,
                    $before,
                    $after,
                    $reason,
                    ['transaction_id' => $transaction->id],
                    $userId,
                );
            }

            return ['transaction' => $transaction, 'wallet' => $wallet->fresh()];
        });
    }

    /**
     * @return array{transaction: CustomerLoyaltyTransaction, wallet: CustomerLoyaltyWallet}
     */
    public function debit(
        CustomerLoyaltyWallet $wallet,
        int $points,
        LoyaltyTransactionType $type,
        LoyaltyTransactionStatus $status,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $userId = null,
        ?LoyaltyEventType $eventType = null,
        string $counterField = 'redeemed_points',
    ): array {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Debit points must be positive.');
        }

        return DB::transaction(function () use ($wallet, $points, $type, $status, $referenceType, $referenceId, $reason, $userId, $eventType, $counterField) {
            $wallet = CustomerLoyaltyWallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $before = (int) $wallet->available_points;

            if ($status !== LoyaltyTransactionStatus::PendingApproval && $before < $points) {
                throw new \RuntimeException(__('Insufficient loyalty points.'));
            }

            if ($status === LoyaltyTransactionStatus::PendingApproval) {
                $after = $before;
            } else {
                $wallet->update([
                    'available_points' => $before - $points,
                    $counterField => $wallet->{$counterField} + $points,
                ]);
                $after = $before - $points;
            }

            $transaction = CustomerLoyaltyTransaction::query()->create([
                'tenant_id' => $wallet->tenant_id,
                'customer_id' => $wallet->customer_id,
                'program_id' => $wallet->program_id,
                'wallet_id' => $wallet->id,
                'branch_id' => $wallet->branch_id,
                'transaction_type' => $type,
                'points' => -$points,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason,
                'status' => $status,
                'created_by' => $userId,
            ]);

            if ($status === LoyaltyTransactionStatus::Completed && $eventType !== null) {
                $wallet->loadMissing('program');
                $this->timeline->record(
                    $wallet->customer_id,
                    $wallet->program,
                    $eventType,
                    -$points,
                    $before,
                    $after,
                    $reason,
                    ['transaction_id' => $transaction->id],
                    $userId,
                );
            }

            return ['transaction' => $transaction, 'wallet' => $wallet->fresh()];
        });
    }

    public function approvePendingCredit(CustomerLoyaltyTransaction $transaction, int $approverId): CustomerLoyaltyTransaction
    {
        return DB::transaction(function () use ($transaction, $approverId) {
            $transaction = CustomerLoyaltyTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status !== LoyaltyTransactionStatus::PendingApproval) {
                return $transaction;
            }

            $points = abs((int) $transaction->points);
            $wallet = CustomerLoyaltyWallet::query()->lockForUpdate()->findOrFail($transaction->wallet_id);
            $before = (int) $wallet->available_points;

            $wallet->update([
                'pending_points' => max(0, $wallet->pending_points - $points),
                'available_points' => $wallet->available_points + $points,
                'lifetime_earned_points' => $wallet->lifetime_earned_points + $points,
            ]);

            $after = $before + $points;

            $transaction->update([
                'status' => LoyaltyTransactionStatus::Completed,
                'balance_after' => $after,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            $wallet->loadMissing('program');
            $this->timeline->record(
                $wallet->customer_id,
                $wallet->program,
                LoyaltyEventType::Approval,
                $points,
                $before,
                $after,
                __('Approved by manager'),
                ['transaction_id' => $transaction->id],
                $approverId,
            );

            return $transaction->fresh();
        });
    }

    public function approvePendingDebit(CustomerLoyaltyTransaction $transaction, int $approverId): CustomerLoyaltyTransaction
    {
        return DB::transaction(function () use ($transaction, $approverId) {
            $transaction = CustomerLoyaltyTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status !== LoyaltyTransactionStatus::PendingApproval) {
                return $transaction;
            }

            $points = abs((int) $transaction->points);
            $wallet = CustomerLoyaltyWallet::query()->lockForUpdate()->findOrFail($transaction->wallet_id);
            $before = (int) $wallet->available_points;

            if ($before < $points) {
                throw new \RuntimeException(__('Insufficient loyalty points.'));
            }

            $wallet->update([
                'available_points' => $before - $points,
                'redeemed_points' => $wallet->redeemed_points + $points,
            ]);

            $after = $before - $points;

            $transaction->update([
                'status' => LoyaltyTransactionStatus::Completed,
                'balance_after' => $after,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            $wallet->loadMissing('program');
            $this->timeline->record(
                $wallet->customer_id,
                $wallet->program,
                LoyaltyEventType::Approval,
                -$points,
                $before,
                $after,
                __('Redemption approved by manager'),
                ['transaction_id' => $transaction->id],
                $approverId,
            );

            return $transaction->fresh();
        });
    }
}
