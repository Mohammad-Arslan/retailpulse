<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_id',
    'tenant_id',
    'period_start',
    'period_end',
    'on_time_delivery_rate',
    'quality_rejection_rate',
    'average_lead_time_days',
    'score',
])]
class SupplierPerformanceScore extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'on_time_delivery_rate' => 'decimal:2',
            'quality_rejection_rate' => 'decimal:2',
            'average_lead_time_days' => 'decimal:2',
            'score' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
