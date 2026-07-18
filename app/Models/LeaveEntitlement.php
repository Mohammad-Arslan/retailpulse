<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'leave_type_id',
    'fiscal_year_id',
    'accrued_days',
    'used_days',
    'encashed_days',
    'carried_forward_days',
    'accrual_last_run_on',
])]
final class LeaveEntitlement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accrued_days' => 'decimal:2',
            'used_days' => 'decimal:2',
            'encashed_days' => 'decimal:2',
            'carried_forward_days' => 'decimal:2',
            'accrual_last_run_on' => 'date',
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

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return Attribute<float, never>
     */
    protected function remainingDays(): Attribute
    {
        return Attribute::get(function (): float {
            return (float) $this->accrued_days
                + (float) $this->carried_forward_days
                - (float) $this->used_days
                - (float) $this->encashed_days;
        });
    }
}
