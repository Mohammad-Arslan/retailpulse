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
    'fiscal_year_id',
    'employee_id',
    'period_label',
    'status',
    'totals_json',
    'executed_by',
    'executed_at',
])]
final class LeaveYearEndRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'totals_json' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function executedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LeaveYearEndLine::class);
    }
}
