<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyEventType;
use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\Sale;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LoyaltyEarnService
{
    public function __construct(
        private readonly LoyaltyProgramService $programs,
        private readonly LoyaltyRuleEngine $ruleEngine,
        private readonly LoyaltyWalletService $wallets,
        private readonly LoyaltyTierService $tiers,
        private readonly LoyaltyApprovalService $approvals,
    ) {}

    public function earnOnSaleComplete(Sale $sale): ?CustomerLoyaltyTransaction
    {
        if (! (bool) SystemSetting::get('loyalty', 'enabled', true)) {
            return null;
        }

        if ($sale->customer_id === null) {
            return null;
        }

        $program = $this->programs->resolveActiveProgramForBranch((int) $sale->branch_id);

        if ($program === null) {
            return null;
        }

        $walletBranchId = $program->earn_scope->value === 'branch' ? (int) $sale->branch_id : null;

        try {
            $this->programs->assertBranchCanEarn($program, (int) $sale->branch_id, $walletBranchId);
        } catch (ValidationException) {
            return null;
        }

        $wallet = $this->wallets->getOrCreateWallet(
            (int) $sale->customer_id,
            $program,
            $walletBranchId,
        );

        $tierMultiplier = $this->tiers->getTierMultiplier($wallet);
        $evaluation = $this->ruleEngine->evaluateForSale($sale, $program, $tierMultiplier);
        $points = $evaluation['points'];

        if ($points <= 0) {
            return null;
        }

        $requiresApproval = $this->approvals->requiresApproval(
            $program,
            LoyaltyApprovalActionType::BonusPoints,
            $points,
        );

        $status = $requiresApproval
            ? LoyaltyTransactionStatus::PendingApproval
            : LoyaltyTransactionStatus::Completed;

        return DB::transaction(function () use ($wallet, $points, $sale, $status) {
            $result = $this->wallets->credit(
                $wallet,
                $points,
                LoyaltyTransactionType::Earn,
                $status,
                Sale::class,
                $sale->id,
                __('Points earned on sale #:id', ['id' => $sale->id]),
                $sale->cashier_id,
                $status === LoyaltyTransactionStatus::Completed ? LoyaltyEventType::Purchase : null,
            );

            $updatedWallet = $this->tiers->recalculateForWallet($result['wallet']);

            return $result['transaction'];
        });
    }
}
