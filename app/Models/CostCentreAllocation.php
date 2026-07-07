<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CostCentreAllocationMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source_journal_transaction_id',
    'cost_centre_id',
    'allocation_method',
    'allocation_percent',
    'allocated_amount',
])]
class CostCentreAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'allocation_method' => CostCentreAllocationMethod::class,
            'allocation_percent' => 'decimal:4',
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(JournalTransaction::class, 'source_journal_transaction_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
