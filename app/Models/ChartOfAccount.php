<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChartOfAccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'code',
    'name',
    'type',
    'parent_id',
    'account_level',
    'is_group',
    'is_postable',
    'branch_id',
    'legal_entity_id',
    'currency_code',
    'status',
    'effective_from',
    'effective_to',
    'created_by',
    'updated_by',
])]
class ChartOfAccount extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ChartOfAccountType::class,
            'is_group' => 'boolean',
            'is_postable' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }
}
