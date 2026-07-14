<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'legal_entity_id',
    'status',
])]
final class SalaryStructure extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class)->orderBy('sequence');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
