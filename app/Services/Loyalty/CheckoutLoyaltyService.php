<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyTransactionStatus;
use App\Models\Sale;
use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

final class CheckoutLoyaltyService
{
    public function __construct(
        private readonly LoyaltyProgramService $programs,
        private readonly LoyaltyWalletService $wallets,
        private readonly LoyaltyRedemptionService $redemption,
    ) {}

    public function applyRedemptionToSale(Sale $sale, int $points, int $cashierId): void
    {
        if (! (bool) SystemSetting::get('loyalty', 'enabled', true)) {
            return;
        }

        if ($sale->customer_id === null || $points <= 0) {
            return;
        }

        $program = $this->programs->resolveActiveProgramForBranch((int) $sale->branch_id);

        if ($program === null) {
            throw ValidationException::withMessages([
                'loyalty_points_to_redeem' => __('No active loyalty program for this branch.'),
            ]);
        }

        $walletBranchId = $program->redeem_scope->value === 'branch' ? (int) $sale->branch_id : null;
        $wallet = $this->wallets->getOrCreateWallet((int) $sale->customer_id, $program, $walletBranchId);

        $transaction = $this->redemption->redeem(
            $wallet,
            $program,
            $points,
            (int) $sale->branch_id,
            $cashierId,
            (float) $sale->grand_total,
            Sale::class,
            $sale->id,
        );

        if ($transaction->status === LoyaltyTransactionStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'loyalty_points_to_redeem' => __('This redemption requires manager approval and cannot be applied at checkout.'),
            ]);
        }

        $config = $this->redemption->resolveRedemptionConfig($program) ?? [];
        $discount = $this->redemption->pointsToCurrency($points, $config);

        if ($discount <= 0) {
            return;
        }

        $sale->update([
            'total_discount' => round((float) $sale->total_discount + $discount, 2),
            'grand_total' => round(max(0, (float) $sale->grand_total - $discount), 2),
            'balance_due' => round(max(0, (float) $sale->balance_due - $discount), 2),
        ]);
    }
}
