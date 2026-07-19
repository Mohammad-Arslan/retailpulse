<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WarehouseType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'branch_id',
    'name',
    'code',
    'type',
    'is_default',
    'is_active',
])]
class Warehouse extends Model
{
    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
