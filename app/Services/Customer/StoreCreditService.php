<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\StoreCreditTransactionReason;
use App\Enums\StoreCreditTransactionType;
use App\Models\Sale;
use App\Models\StoreCredit;
use App\Models\StoreCreditTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreCreditService
{
    public function getAvailableBalance(int $customerId): float
    {
        return (float) StoreCredit::query()
            ->where('customer_id', $customerId)
            ->where('balance', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('balance');
    }

    public function issue(
        int $customerId,
        float $amount,
        ?int $sourceSaleId,
        int $userId,
        ?string $notes = null,
    ): StoreCredit {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Store credit amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($customerId, $amount, $sourceSaleId, $userId, $notes) {
            $credit = StoreCredit::query()->create([
                'customer_id' => $customerId,
                'balance' => $amount,
                'expires_at' => null,
                'source_sale_id' => $sourceSaleId,
                'notes' => $notes,
            ]);

            StoreCreditTransaction::query()->create([
                'store_credit_id' => $credit->id,
                'amount' => $amount,
                'type' => StoreCreditTransactionType::Credit,
                'reason' => StoreCreditTransactionReason::Return,
                'reference_type' => $sourceSaleId !== null ? Sale::class : null,
                'reference_id' => $sourceSaleId,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            return $credit;
        });
    }

    public function redeemForCheckout(int $customerId, float $amount, int $saleId, int $userId): float
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Redemption amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($customerId, $amount, $saleId, $userId) {
            $remaining = $amount;

            $credits = StoreCredit::query()
                ->where('customer_id', $customerId)
                ->where('balance', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $available = $credits->sum(fn (StoreCredit $credit) => (float) $credit->balance);

            if ($available < $amount) {
                throw ValidationException::withMessages([
                    'amount' => __('Insufficient store credit balance.'),
                ]);
            }

            foreach ($credits as $credit) {
                if ($remaining <= 0) {
                    break;
                }

                $debit = min((float) $credit->balance, $remaining);

                $credit->update(['balance' => (float) $credit->balance - $debit]);

                StoreCreditTransaction::query()->create([
                    'store_credit_id' => $credit->id,
                    'amount' => $debit,
                    'type' => StoreCreditTransactionType::Debit,
                    'reason' => StoreCreditTransactionReason::Checkout,
                    'reference_type' => Sale::class,
                    'reference_id' => $saleId,
                    'user_id' => $userId,
                    'created_at' => now(),
                ]);

                $remaining -= $debit;
            }

            return $amount;
        });
    }
}
