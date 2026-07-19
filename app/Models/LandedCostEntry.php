<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LandedCostAllocationMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'grn_id',
    'charge_type',
    'description',
    'amount',
    'currency_code',
    'exchange_rate',
    'functional_amount',
    'allocation_method',
    'created_by',
    'updated_by',
])]
class LandedCostEntry extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'functional_amount' => 'decimal:2',
            'allocation_method' => LandedCostAllocationMethod::class,
        ];
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivingNote::class, 'grn_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }
}
