<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legal_entity_id',
    'code',
    'name',
    'rank',
    'currency_code',
    'min_amount',
    'mid_amount',
    'max_amount',
    'enforce_salary_band',
    'effective_from',
    'effective_to',
    'status',
])]
class Grade extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'min_amount' => 'decimal:4',
            'mid_amount' => 'decimal:4',
            'max_amount' => 'decimal:4',
            'enforce_salary_band' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
