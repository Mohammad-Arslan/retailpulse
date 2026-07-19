<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'code',
    'name',
    'legal_entity_id',
    'calculation_type',
    'employee_rate',
    'employer_rate',
    'wage_ceiling',
    'account_mapping_key_employee',
    'account_mapping_key_employer',
    'effective_from',
    'effective_to',
    'status',
])]
final class StatutoryScheme extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_rate' => 'decimal:6',
            'employer_rate' => 'decimal:6',
            'wage_ceiling' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }
}
