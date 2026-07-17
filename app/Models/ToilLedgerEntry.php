<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'entry_type',
    'hours',
    'earned_date',
    'expires_at',
    'overtime_record_id',
    'toil_claim_id',
    'credit_entry_id',
    'notes',
    'created_by',
])]
final class ToilLedgerEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'earned_date' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function overtimeRecord(): BelongsTo
    {
        return $this->belongsTo(OvertimeRecord::class);
    }

    public function toilClaim(): BelongsTo
    {
        return $this->belongsTo(ToilClaim::class);
    }

    public function creditEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_entry_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
