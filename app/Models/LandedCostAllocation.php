<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'landed_cost_entry_id',
    'grn_item_id',
    'allocated_amount',
    'functional_amount',
])]
class LandedCostAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:4',
            'functional_amount' => 'decimal:4',
        ];
    }

    public function landedCostEntry(): BelongsTo
    {
        return $this->belongsTo(LandedCostEntry::class);
    }

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GrnItem::class);
    }
}
