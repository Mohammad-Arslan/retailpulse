<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyEventType;
use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyProgram;
use Illuminate\Validation\ValidationException;

final class LoyaltyRedemptionService
{
    public function __construct(
        private readonly LoyaltyProgramService $programs,
        private readonly LoyaltyRuleEngine $ruleEngine,
        private readonly LoyaltyWalletService $wallets,
        private readonly LoyaltyApprovalService $approvals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function redemptionOptions(CustomerLoyaltyWallet $wallet, LoyaltyProgram $program): array
    {
        $config = $this->ruleEngine->resolveRedemptionConfig($program) ?? [];

        $pointsPerUnit = (float) ($config['points_per_unit'] ?? 100);
        $currencyPerUnit = (float) ($config['currency_per_unit'] ?? 100);
        $available = (int) $wallet->available_points;

        return [
            'available_points' => $available,
            'min_redeem_points' => (int) ($config['min_redeem_points'] ?? 0),
            'max_redeem_percent' => (float) ($config['max_redeem_percent'] ?? 100),
            'max_redeem_amount' => isset($config['max_redeem_amount']) ? (float) $config['max_redeem_amount'] : null,
            'conversion_rate' => [
                'points' => $pointsPerUnit,
                'currency' => $currencyPerUnit,
            ],
            'max_discount' => $this->calculateMaxDiscount($available, $config),
            'allowed_product_ids' => array_map('intval', (array) ($config['allowed_product_ids'] ?? [])),
            'allowed_category_ids' => array_map('intval', (array) ($config['allowed_category_ids'] ?? [])),
            'allowed_branch_ids' => array_map('intval', (array) ($config['allowed_branch_ids'] ?? [])),
        ];
    }

    public function redeem(
        CustomerLoyaltyWallet $wallet,
        LoyaltyProgram $program,
        int $points,
        int $branchId,
        ?int $userId = null,
        ?float $saleTotal = null,
    ): CustomerLoyaltyTransaction {
        $config = $this->ruleEngine->resolveRedemptionConfig($program);

        if ($config === null) {
            throw ValidationException::withMessages([
                'points' => __('No redemption rules configured for this program.'),
            ]);
        }

        $this->programs->assertBranchCanRedeem($program, $branchId, $wallet->branch_id);

        $minPoints = (int) ($config['min_redeem_points'] ?? 0);
        if ($points < $minPoints) {
            throw ValidationException::withMessages([
                'points' => __('Minimum redemption is :min points.', ['min' => $minPoints]),
            ]);
        }

        if ($points > (int) $wallet->available_points) {
            throw ValidationException::withMessages([
                'points' => __('Insufficient loyalty points.'),
            ]);
        }

        $discount = $this->pointsToCurrency($points, $config);

        if ($saleTotal !== null) {
            $maxPercent = (float) ($config['max_redeem_percent'] ?? 100);
            $maxByPercent = $saleTotal * ($maxPercent / 100);
            if ($discount > $maxByPercent) {
                throw ValidationException::withMessages([
                    'points' => __('Redemption exceeds maximum allowed percent of sale.'),
                ]);
            }
        }

        if (isset($config['max_redeem_amount']) && $discount > (float) $config['max_redeem_amount']) {
            throw ValidationException::withMessages([
                'points' => __('Redemption exceeds maximum allowed amount.'),
            ]);
        }

        $requiresApproval = $this->approvals->requiresApproval(
            $program,
            LoyaltyApprovalActionType::LargeRedemption,
            $points,
            $discount,
        );

        $status = $requiresApproval
            ? LoyaltyTransactionStatus::PendingApproval
            : LoyaltyTransactionStatus::Completed;

        $result = $this->wallets->debit(
            $wallet,
            $points,
            LoyaltyTransactionType::Redeem,
            $status,
            null,
            null,
            __('Redeemed :points points for :amount discount', [
                'points' => $points,
                'amount' => number_format($discount, 2),
            ]),
            $userId,
            $status === LoyaltyTransactionStatus::Completed ? LoyaltyEventType::Redeem : null,
        );

        return $result['transaction'];
    }

    public function adjustPoints(
        CustomerLoyaltyWallet $wallet,
        int $points,
        string $reason,
        int $userId,
        LoyaltyProgram $program,
    ): CustomerLoyaltyTransaction {
        $requiresApproval = $this->approvals->requiresApproval(
            $program,
            LoyaltyApprovalActionType::ManualAdjustment,
            abs($points),
        );

        $status = $requiresApproval
            ? LoyaltyTransactionStatus::PendingApproval
            : LoyaltyTransactionStatus::Completed;

        if ($points >= 0) {
            $result = $this->wallets->credit(
                $wallet,
                $points,
                LoyaltyTransactionType::Adjustment,
                $status,
                null,
                null,
                $reason,
                $userId,
                $status === LoyaltyTransactionStatus::Completed ? LoyaltyEventType::Adjustment : null,
            );
        } else {
            $result = $this->wallets->debit(
                $wallet,
                abs($points),
                LoyaltyTransactionType::Adjustment,
                $status,
                null,
                null,
                $reason,
                $userId,
                $status === LoyaltyTransactionStatus::Completed ? LoyaltyEventType::Adjustment : null,
            );
        }

        return $result['transaction'];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function pointsToCurrency(int $points, array $config): float
    {
        $pointsPerUnit = (float) ($config['points_per_unit'] ?? 100);
        $currencyPerUnit = (float) ($config['currency_per_unit'] ?? 100);

        if ($pointsPerUnit <= 0) {
            return 0.0;
        }

        return round(($points / $pointsPerUnit) * $currencyPerUnit, 2);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function calculateMaxDiscount(int $availablePoints, array $config): float
    {
        $discount = $this->pointsToCurrency($availablePoints, $config);

        if (isset($config['max_redeem_amount'])) {
            $discount = min($discount, (float) $config['max_redeem_amount']);
        }

        return $discount;
    }
}
