<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CountScheduleFrequency;
use App\Enums\CountScopeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'branch_id',
    'warehouse_id',
    'scope_type',
    'scope_id',
    'frequency',
    'day_of_week',
    'day_of_month',
    'blind_count',
    'freeze_mode',
    'is_active',
    'last_run_at',
])]
class CountScheduleRule extends Model
{
    protected function casts(): array
    {
        return [
            'scope_type' => CountScopeType::class,
            'frequency' => CountScheduleFrequency::class,
            'blind_count' => 'boolean',
            'freeze_mode' => 'boolean',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
