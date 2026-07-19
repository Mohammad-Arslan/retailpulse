<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'legal_entity_id',
    'branch_id',
    'daily_threshold_minutes',
    'weekly_threshold_minutes',
    'rest_day_applies',
    'public_holiday_applies',
    'toil_expiry_months',
    'effective_from',
    'effective_to',
    'status',
    'priority',
])]
final class OvertimePolicy extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_threshold_minutes' => 'integer',
            'weekly_threshold_minutes' => 'integer',
            'rest_day_applies' => 'boolean',
            'public_holiday_applies' => 'boolean',
            'toil_expiry_months' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'priority' => 'integer',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function multipliers(): HasMany
    {
        return $this->hasMany(OvertimeMultiplier::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(OvertimeRecord::class);
    }
}
