<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'legal_entity_id',
    'interbranch_accounting_enabled',
    'due_from_account_id',
    'due_to_account_id',
    'status',
    'accounting_enabled_modules',
])]
class BranchAccountingProfile extends Model
{
    protected function casts(): array
    {
        return [
            'interbranch_accounting_enabled' => 'boolean',
            'accounting_enabled_modules' => 'array',
        ];
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
