<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'phone',
    'email',
    'ntn',
    'cnic',
    'is_active',
    'loyalty_tier_id',
    'customer_group_id',
    'credit_limit',
    'preferred_payment_method',
    'notes',
])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function loyaltyTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class);
    }

    public function storeCredits(): HasMany
    {
        return $this->hasMany(StoreCredit::class);
    }

    public function arLedger(): HasMany
    {
        return $this->hasMany(CustomerArLedger::class);
    }

    public function totalLoyaltyPoints(): int
    {
        return (int) $this->loyaltyPoints()->sum('points');
    }

    public function walletBalance(): float
    {
        $wallet = $this->relationLoaded('wallet') ? $this->wallet : $this->wallet()->first();

        if ($wallet === null || $wallet->isExpired()) {
            return 0.0;
        }

        return (float) $wallet->balance;
    }

    public function storeCreditBalance(): float
    {
        return (float) $this->storeCredits()
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('balance');
    }
}
