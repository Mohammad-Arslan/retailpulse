<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'snapshot_date',
    'customer_id',
    'branch_id',
    'current',
    'bucket_30',
    'bucket_60',
    'bucket_90',
    'bucket_over_90',
    'total_outstanding',
])]
class ArAgingSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'current' => 'decimal:2',
            'bucket_30' => 'decimal:2',
            'bucket_60' => 'decimal:2',
            'bucket_90' => 'decimal:2',
            'bucket_over_90' => 'decimal:2',
            'total_outstanding' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
