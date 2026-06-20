<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'warehouse_id',
    'warehouse_zone_id',
    'zone',
    'aisle',
    'shelf',
    'bin_code',
    'is_active',
    'capacity_limit',
])]
class BinLocation extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity_limit' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseZone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function displayLabel(): string
    {
        $parts = array_filter([$this->zone, $this->aisle, $this->shelf, $this->bin_code]);

        return implode('-', $parts);
    }
}
