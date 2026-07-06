<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'customer_id',
    'program_id',
    'branch_id',
    'tier_id',
    'available_points',
    'pending_points',
    'redeemed_points',
    'expired_points',
    'lifetime_earned_points',
])]
class CustomerLoyaltyWallet extends Model
{
    protected function casts(): array
    {
        return [
            'available_points' => 'integer',
            'pending_points' => 'integer',
            'redeemed_points' => 'integer',
            'expired_points' => 'integer',
            'lifetime_earned_points' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgramTier::class, 'tier_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyTransaction::class, 'wallet_id');
    }
}
