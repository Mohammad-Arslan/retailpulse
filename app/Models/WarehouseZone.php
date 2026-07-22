<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'warehouse_id', 'name', 'code', 'capacity_limit', 'is_active'])]
class WarehouseZone extends Model
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

    public function binLocations(): HasMany
    {
        return $this->hasMany(BinLocation::class);
    }
}
