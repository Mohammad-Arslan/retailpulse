<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExchangeRateType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'currency_id',
    'rate_date',
    'rate_type',
    'rate',
    'source',
    'approved_by',
    'status',
])]
class ExchangeRate extends Model
{
    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate_type' => ExchangeRateType::class,
            'rate' => 'decimal:6',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
