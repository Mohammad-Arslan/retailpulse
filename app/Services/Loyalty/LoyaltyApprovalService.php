<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyApprovalMode;
use App\Enums\LoyaltyApprovalThresholdType;
use App\Enums\LoyaltyEventType;
use App\Enums\LoyaltyTransactionStatus;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\LoyaltyApprovalPolicy;
use App\Models\LoyaltyProgram;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PosPinService;
use Illuminate\Validation\ValidationException;

final class LoyaltyApprovalService
{
    public function __construct(
        private readonly LoyaltyWalletService $wallets,
        private readonly LoyaltyTimelineService $timeline,
        private readonly PosPinService $pinService,
    ) {}

    public function requiresApproval(
        LoyaltyProgram $program,
        LoyaltyApprovalActionType $actionType,
        int $points,
        ?float $currencyAmount = null,
    ): bool {
        $policy = $this->resolvePolicy($program, $actionType);

        if ($policy === null) {
            return false;
        }

        $threshold = (float) $policy->threshold_value;

        return match ($policy->threshold_type) {
            LoyaltyApprovalThresholdType::Points => $points >= $threshold,
            LoyaltyApprovalThresholdType::Currency => $currencyAmount !== null && $currencyAmount >= $threshold,
        };
    }

    public function approveWithPin(
        CustomerLoyaltyTransaction $transaction,
        User $approver,
        string $pin,
    ): CustomerLoyaltyTransaction {
        if (! $approver->can('loyalty.approve')) {
            throw ValidationException::withMessages([
                'pin' => __('You are not authorized to approve loyalty transactions.'),
            ]);
        }

        $policy = $this->resolvePolicy(
            $transaction->program,
            $this->actionTypeForTransaction($transaction),
        );

        if ($policy !== null && $policy->approval_mode === LoyaltyApprovalMode::Workflow) {
            if ((bool) SystemSetting::get('loyalty', 'workflow_enabled', false)) {
                throw ValidationException::withMessages([
                    'approval' => __('Workflow approval is enabled. PIN approval is not available.'),
                ]);
            }
        }

        if (! $this->pinService->verifyPin($approver, $pin)) {
            throw ValidationException::withMessages([
                'pin' => __('Invalid PIN.'),
            ]);
        }

        if ($transaction->points >= 0) {
            return $this->wallets->approvePendingCredit($transaction, $approver->id);
        }

        return $this->wallets->approvePendingDebit($transaction, $approver->id);
    }

    public function reject(CustomerLoyaltyTransaction $transaction, User $approver, ?string $reason = null): CustomerLoyaltyTransaction
    {
        if ($transaction->status !== LoyaltyTransactionStatus::PendingApproval) {
            return $transaction;
        }

        $transaction->update([
            'status' => LoyaltyTransactionStatus::Rejected,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'reason' => $reason ?? $transaction->reason,
        ]);

        $wallet = $transaction->wallet;
        $points = abs((int) $transaction->points);

        if ($transaction->points > 0) {
            $wallet->update([
                'pending_points' => max(0, $wallet->pending_points - $points),
            ]);
        }

        $this->timeline->record(
            $transaction->customer_id,
            $transaction->program,
            LoyaltyEventType::Approval,
            0,
            (int) $wallet->available_points,
            (int) $wallet->available_points,
            __('Transaction rejected by manager'),
            ['transaction_id' => $transaction->id, 'rejected' => true],
            $approver->id,
        );

        return $transaction->fresh();
    }

    private function resolvePolicy(LoyaltyProgram $program, LoyaltyApprovalActionType $actionType): ?LoyaltyApprovalPolicy
    {
        return $program->approvalPolicies()
            ->where('action_type', $actionType)
            ->where('is_active', true)
            ->first();
    }

    private function actionTypeForTransaction(CustomerLoyaltyTransaction $transaction): LoyaltyApprovalActionType
    {
        return match ($transaction->transaction_type->value) {
            'adjustment' => LoyaltyApprovalActionType::ManualAdjustment,
            'bonus' => LoyaltyApprovalActionType::BonusPoints,
            'redeem' => LoyaltyApprovalActionType::LargeRedemption,
            default => LoyaltyApprovalActionType::ManualAdjustment,
        };
    }
}
