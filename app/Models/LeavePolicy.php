<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'leave_type_id',
    'legal_entity_id',
    'accrual_method',
    'accrual_rate',
    'max_balance',
    'carry_forward_limit',
    'carry_forward_expiry_months',
    'proration_on_join',
    'effective_from',
    'effective_to',
    'status',
])]
final class LeavePolicy extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accrual_rate' => 'decimal:4',
            'max_balance' => 'decimal:2',
            'carry_forward_limit' => 'decimal:2',
            'carry_forward_expiry_months' => 'integer',
            'proration_on_join' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }
}
