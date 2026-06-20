<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\WalletTransactionReason;
use App\Enums\WalletTransactionType;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\Sale;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WalletService
{
    public function getOrCreateWallet(int $customerId): CustomerWallet
    {
        return CustomerWallet::query()->firstOrCreate(
            ['customer_id' => $customerId],
            ['balance' => 0, 'expires_at' => null],
        );
    }

    public function getAvailableBalance(int $customerId): float
    {
        $wallet = CustomerWallet::query()->where('customer_id', $customerId)->first();

        if ($wallet === null || $wallet->isExpired()) {
            return 0.0;
        }

        return (float) $wallet->balance;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function topUp(
        int $customerId,
        float $amount,
        int $userId,
        array $meta = [],
        ?string $paymentMethod = null,
    ): CustomerWallet {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Top-up amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($customerId, $amount, $userId, $meta, $paymentMethod) {
            $wallet = $this->getOrCreateWallet($customerId);
            $wallet = CustomerWallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $this->applyExpiryPolicy($wallet);

            $wallet->balance = (float) $wallet->balance + $amount;
            $wallet->save();

            CustomerWalletTransaction::query()->create([
                'customer_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => WalletTransactionType::Credit,
                'reason' => WalletTransactionReason::TopUp,
                'reference_type' => null,
                'reference_id' => null,
                'meta' => array_merge($meta, array_filter([
                    'payment_method' => $paymentMethod,
                ])),
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            $this->refreshWalletExpiry($wallet);

            return $wallet->fresh() ?? $wallet;
        });
    }

    public function debitForCheckout(int $customerId, float $amount, int $saleId, int $userId): CustomerWallet
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Debit amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($customerId, $amount, $saleId, $userId) {
            $wallet = $this->getOrCreateWallet($customerId);
            $wallet = CustomerWallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $this->applyExpiryPolicy($wallet);

            if ($wallet->isExpired()) {
                throw ValidationException::withMessages([
                    'wallet' => __('Customer wallet has expired.'),
                ]);
            }

            if ((float) $wallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => __('Insufficient wallet balance.'),
                ]);
            }

            $wallet->balance = (float) $wallet->balance - $amount;
            $wallet->save();

            CustomerWalletTransaction::query()->create([
                'customer_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => WalletTransactionType::Debit,
                'reason' => WalletTransactionReason::Checkout,
                'reference_type' => Sale::class,
                'reference_id' => $saleId,
                'meta' => null,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            return $wallet->fresh() ?? $wallet;
        });
    }

    public function applyExpiryPolicy(?CustomerWallet $wallet = null): void
    {
        $expiryDays = (int) SystemSetting::get('customers', 'wallet_expiry_days', 0);

        if ($expiryDays <= 0) {
            return;
        }

        $query = CustomerWallet::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('balance', '>', 0);

        if ($wallet !== null) {
            $query->where('id', $wallet->id);
        }

        $query->lockForUpdate()->each(function (CustomerWallet $expiredWallet): void {
            $balance = (float) $expiredWallet->balance;

            if ($balance <= 0) {
                return;
            }

            CustomerWalletTransaction::query()->create([
                'customer_wallet_id' => $expiredWallet->id,
                'amount' => $balance,
                'type' => WalletTransactionType::Debit,
                'reason' => WalletTransactionReason::Expiry,
                'reference_type' => null,
                'reference_id' => null,
                'meta' => ['expired_at' => $expiredWallet->expires_at?->toIso8601String()],
                'user_id' => null,
                'created_at' => now(),
            ]);

            $expiredWallet->update(['balance' => 0]);
        });
    }

    private function refreshWalletExpiry(CustomerWallet $wallet): void
    {
        $expiryDays = (int) SystemSetting::get('customers', 'wallet_expiry_days', 0);

        if ($expiryDays <= 0) {
            $wallet->update(['expires_at' => null]);

            return;
        }

        $wallet->update(['expires_at' => now()->addDays($expiryDays)]);
    }
}
