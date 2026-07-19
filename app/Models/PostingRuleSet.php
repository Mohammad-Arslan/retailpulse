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
    'event_type',
    'entity_type',
    'branch_id',
    'legal_entity_id',
    'currency_code',
    'priority',
    'effective_from',
    'effective_to',
    'status',
    'created_by',
    'updated_by',
])]
class PostingRuleSet extends Model
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PostingRuleLine::class)->orderBy('sequence');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
