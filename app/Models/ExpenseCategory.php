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
    'parent_id',
    'account_mapping_key',
    'is_group',
    'requires_receipt',
    'default_tax_type_id',
    'status',
])]
class ExpenseCategory extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'requires_receipt' => 'boolean',
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

    public function defaultTaxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class, 'default_tax_type_id');
    }

    public function resolvedAccountMappingKey(): string
    {
        return $this->account_mapping_key ?: 'expense_default';
    }
}
