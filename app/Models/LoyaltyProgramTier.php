<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyTierQualificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'program_id',
    'name',
    'tier_level',
    'qualification_type',
    'qualification_value',
    'multiplier',
    'benefits_json',
    'status',
])]
class LoyaltyProgramTier extends Model
{
    protected $table = 'loyalty_program_tiers';

    protected function casts(): array
    {
        return [
            'tier_level' => 'integer',
            'qualification_type' => LoyaltyTierQualificationType::class,
            'qualification_value' => 'decimal:2',
            'multiplier' => 'decimal:2',
            'benefits_json' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyWallet::class, 'tier_id');
    }
}
