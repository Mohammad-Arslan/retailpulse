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
    'code',
    'name',
    'default_grade_id',
    'status',
])]
class Designation extends Model
{
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function defaultGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'default_grade_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
