<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NegativeLeaveBalancePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'leave_type_id',
    'legal_entity_id',
    'accrual_method',
    'accrual_rate',
    'max_balance',
    'carry_forward_limit',
    'carry_forward_expiry_months',
    'negative_leave_balance_policy',
    'proration_on_join',
    'exclude_public_holidays',
    'exclude_weekends',
    'short_leave_max_hours',
    'short_leave_max_requests_per_month',
    'out_station_deducts_balance',
    'encashment_allowed',
    'encashment_max_days',
    'encashment_requires_approval',
    'year_end_excess_disposition',
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
            'negative_leave_balance_policy' => NegativeLeaveBalancePolicy::class,
            'proration_on_join' => 'boolean',
            'exclude_public_holidays' => 'boolean',
            'exclude_weekends' => 'boolean',
            'short_leave_max_hours' => 'decimal:2',
            'short_leave_max_requests_per_month' => 'integer',
            'out_station_deducts_balance' => 'boolean',
            'encashment_allowed' => 'boolean',
            'encashment_max_days' => 'decimal:2',
            'encashment_requires_approval' => 'boolean',
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
