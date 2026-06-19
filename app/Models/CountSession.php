<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CountScopeType;
use App\Enums\CountSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference_no',
    'branch_id',
    'warehouse_id',
    'scope_type',
    'scope_id',
    'status',
    'blind_count',
    'freeze_mode',
    'variance_threshold_pct',
    'variance_threshold_value',
    'created_by',
    'approved_by',
    'posted_at',
])]
class CountSession extends Model
{
    protected function casts(): array
    {
        return [
            'scope_type' => CountScopeType::class,
            'status' => CountSessionStatus::class,
            'blind_count' => 'boolean',
            'freeze_mode' => 'boolean',
            'variance_threshold_pct' => 'decimal:2',
            'variance_threshold_value' => 'decimal:4',
            'posted_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CountSessionLine::class);
    }
}
