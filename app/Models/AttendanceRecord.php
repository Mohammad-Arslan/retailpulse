<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'branch_id',
    'source_id',
    'clock_in',
    'clock_out',
    'worked_minutes',
    'status',
    'is_historical',
    'adjusted_by',
    'adjustment_reason',
])]
final class AttendanceRecord extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'worked_minutes' => 'integer',
            'is_historical' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(AttendanceSource::class, 'source_id');
    }

    public function adjustedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
