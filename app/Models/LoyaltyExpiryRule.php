<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyExpiryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'program_id',
    'expiry_type',
    'value',
    'grace_period_days',
])]
class LoyaltyExpiryRule extends Model
{
    protected function casts(): array
    {
        return [
            'expiry_type' => LoyaltyExpiryType::class,
            'value' => 'integer',
            'grace_period_days' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }
}
