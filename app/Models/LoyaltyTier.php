<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'points_multiplier',
    'min_points',
    'auto_upgrade',
    'sort_order',
    'is_active',
])]
class LoyaltyTier extends Model
{
    protected function casts(): array
    {
        return [
            'points_multiplier' => 'decimal:2',
            'min_points' => 'integer',
            'auto_upgrade' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
