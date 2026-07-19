<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FiscalYearStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'name',
    'legal_entity_id',
    'start_date',
    'end_date',
    'status',
    'closed_at',
    'closed_by',
    'reopened_at',
    'reopened_by',
    'reopen_expires_at',
])]
class FiscalYear extends Model
{
    protected function casts(): array
    {
        return [
            'status' => FiscalYearStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'reopen_expires_at' => 'datetime',
        ];
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function reopenRequests(): HasMany
    {
        return $this->hasMany(FiscalYearReopenRequest::class);
    }

    public function containsDate(\DateTimeInterface $date): bool
    {
        return $date >= $this->start_date && $date <= $this->end_date;
    }
}
