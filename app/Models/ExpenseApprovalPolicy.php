<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'branch_id',
    'expense_category_id',
    'legal_entity_id',
    'min_amount',
    'requires',
    'approver_role',
    'effective_from',
    'effective_to',
    'priority',
    'status',
])]
class ExpenseApprovalPolicy extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'priority' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }
}
