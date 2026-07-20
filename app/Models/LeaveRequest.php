<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'employee_id',
    'leave_type_id',
    'start_date',
    'end_date',
    'duration_type',
    'session',
    'start_time',
    'end_time',
    'days',
    'deduct_from_balance',
    'balance_warning',
    'reason',
    'status',
    'approval_chain_json',
])]
final class LeaveRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:2',
            'deduct_from_balance' => 'boolean',
            'balance_warning' => 'boolean',
            'approval_chain_json' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(LeaveRequestReschedule::class);
    }

    public function toilClaim(): HasOne
    {
        return $this->hasOne(ToilClaim::class);
    }
}
