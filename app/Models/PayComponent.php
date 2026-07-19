<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'code',
    'name',
    'type',
    'calculation_type',
    'basis_component_id',
    'rate',
    'formula_expression',
    'taxable',
    'account_mapping_key',
    'effective_from',
    'effective_to',
    'legal_entity_id',
    'status',
])]
final class PayComponent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taxable' => 'boolean',
            'rate' => 'decimal:6',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function basisComponent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'basis_component_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function salaryStructureComponents(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class);
    }
}
