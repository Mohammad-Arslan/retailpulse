<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'employee_id',
    'date',
    'regular_minutes',
    'overtime_minutes',
    'day_type',
    'resolved_multiplier',
    'compensation_choice',
    'overtime_policy_id',
    'approved_by',
    'status',
])]
final class OvertimeRecord extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'regular_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'resolved_multiplier' => 'decimal:4',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(OvertimePolicy::class, 'overtime_policy_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function toilLedgerEntries(): HasMany
    {
        return $this->hasMany(ToilLedgerEntry::class);
    }
}
