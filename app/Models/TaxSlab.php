<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'legal_entity_id',
    'effective_from',
    'effective_to',
    'lower_bound',
    'upper_bound',
    'fixed_amount',
    'marginal_rate',
    'status',
])]
final class TaxSlab extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'lower_bound' => 'decimal:4',
            'upper_bound' => 'decimal:4',
            'fixed_amount' => 'decimal:4',
            'marginal_rate' => 'decimal:6',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }
}
