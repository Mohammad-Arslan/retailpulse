<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'mapping_key',
    'account_id',
    'branch_id',
    'warehouse_id',
    'product_category_id',
    'payment_method',
    'currency_code',
    'legal_entity_id',
    'effective_from',
    'effective_to',
    'status',
    'priority',
])]
class AccountMapping extends Model
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
