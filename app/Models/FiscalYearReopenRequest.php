<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'fiscal_year_id',
    'reason',
    'requested_by',
    'first_approved_by',
    'second_approved_by',
    'status',
])]
class FiscalYearReopenRequest extends Model
{
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function firstApprovedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_approved_by');
    }

    public function secondApprovedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_approved_by');
    }
}
