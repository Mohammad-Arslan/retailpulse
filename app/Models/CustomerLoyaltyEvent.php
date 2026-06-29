<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'program_id',
    'event_type',
    'points',
    'before_balance',
    'after_balance',
    'description',
    'metadata_json',
    'user_id',
])]
class CustomerLoyaltyEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'event_type' => LoyaltyEventType::class,
            'points' => 'integer',
            'before_balance' => 'integer',
            'after_balance' => 'integer',
            'metadata_json' => 'array',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
