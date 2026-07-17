<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PickingStrategy;
use App\Enums\WarehouseType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'name',
    'code',
    'address',
    'currency',
    'timezone',
    'picking_strategy',
    'operating_hours',
    'receipt_footer',
    'is_active',
    'cutover_date',
    'weekend_days',
])]
class Branch extends Model
{
    protected function casts(): array
    {
        return [
            'picking_strategy' => PickingStrategy::class,
            'operating_hours' => 'array',
            'is_active' => 'boolean',
            'cutover_date' => 'datetime',
            'weekend_days' => 'array',
        ];
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function defaultWarehouse(): ?Warehouse
    {
        return $this->warehouses()->where('is_default', true)->first();
    }

    /**
     * @return HasMany<Warehouse, $this>
     */
    public function warehousesOfType(WarehouseType $type): HasMany
    {
        return $this->warehouses()->where('type', $type->value);
    }
}
