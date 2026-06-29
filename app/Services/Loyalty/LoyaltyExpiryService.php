<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\Enums\LoyaltyEventType;
use App\Enums\LoyaltyExpiryType;
use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyExpiryRule;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LoyaltyExpiryService
{
    public function __construct(
        private readonly LoyaltyWalletService $wallets,
    ) {}

    public function processProgram(LoyaltyProgram $program): int
    {
        $rule = $program->expiryRules()->first();

        if ($rule === null || $rule->expiry_type === LoyaltyExpiryType::Never) {
            return 0;
        }

        $expiredCount = 0;
        $cutoff = $this->resolveCutoffDate($rule);

        if ($cutoff === null) {
            return 0;
        }

        $wallets = $program->wallets()
            ->where('available_points', '>', 0)
            ->get();

        foreach ($wallets as $wallet) {
            $pointsToExpire = $this->calculateExpiringPoints($wallet, $cutoff, $rule);

            if ($pointsToExpire <= 0) {
                continue;
            }

            $this->expirePoints($wallet, $pointsToExpire);
            $expiredCount++;
        }

        return $expiredCount;
    }

    public function processAllPrograms(): int
    {
        $total = 0;

        LoyaltyProgram::query()
            ->where('status', 'active')
            ->chunkById(50, function (Collection $programs) use (&$total) {
                foreach ($programs as $program) {
                    $total += $this->processProgram($program);
                }
            });

        return $total;
    }

    private function expirePoints(CustomerLoyaltyWallet $wallet, int $points): CustomerLoyaltyTransaction
    {
        return DB::transaction(function () use ($wallet, $points) {
            $result = $this->wallets->debit(
                $wallet,
                $points,
                LoyaltyTransactionType::Expire,
                LoyaltyTransactionStatus::Completed,
                null,
                null,
                __('Points expired per program policy'),
                null,
                LoyaltyEventType::Expire,
                'expired_points',
            );

            return $result['transaction'];
        });
    }

    private function resolveCutoffDate(LoyaltyExpiryRule $rule): ?Carbon
    {
        $graceDays = (int) $rule->grace_period_days;

        return match ($rule->expiry_type) {
            LoyaltyExpiryType::FixedDays => now()->subDays((int) ($rule->value ?? 0) + $graceDays),
            LoyaltyExpiryType::FixedMonths => now()->subMonths((int) ($rule->value ?? 0))->subDays($graceDays),
            LoyaltyExpiryType::FiscalYear => $this->fiscalYearCutoff($graceDays),
            LoyaltyExpiryType::Never => null,
        };
    }

    private function fiscalYearCutoff(int $graceDays): Carbon
    {
        $year = now()->month >= 7 ? now()->year : now()->year - 1;

        return Carbon::create($year, 6, 30)->endOfDay()->subDays($graceDays);
    }

    private function calculateExpiringPoints(
        CustomerLoyaltyWallet $wallet,
        Carbon $cutoff,
        LoyaltyExpiryRule $rule,
    ): int {
        $earnedSinceCutoff = CustomerLoyaltyTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('transaction_type', [
                LoyaltyTransactionType::Earn,
                LoyaltyTransactionType::Bonus,
                LoyaltyTransactionType::Adjustment,
            ])
            ->where('status', LoyaltyTransactionStatus::Completed)
            ->where('points', '>', 0)
            ->where('created_at', '>=', $cutoff)
            ->sum('points');

        $available = (int) $wallet->available_points;
        $olderBalance = max(0, $available - (int) $earnedSinceCutoff);

        return $olderBalance;
    }
}
