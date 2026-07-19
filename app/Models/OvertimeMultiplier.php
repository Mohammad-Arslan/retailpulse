<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'overtime_policy_id',
    'day_type',
    'multiplier',
    'compensation_type',
])]
final class OvertimeMultiplier extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(OvertimePolicy::class, 'overtime_policy_id');
    }
}
