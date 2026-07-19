<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosCartStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'cashier_id',
    'branch_id',
    'status',
    'slot',
    'notes',
    'suspended_at',
    'completed_at',
    'voided_at',
])]
class PosCart extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => PosCartStatus::class,
            'slot' => 'integer',
            'suspended_at' => 'datetime',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosCartItem::class, 'cart_id');
    }

    public function subtotal(): float
    {
        return (float) $this->items->sum('line_total');
    }

    public function totalDiscount(): float
    {
        return (float) $this->items->sum(function (PosCartItem $item) {
            $discount = match ($item->discount_type) {
                'flat' => (float) ($item->discount_value ?? 0),
                'percent' => (float) $item->unit_price * $item->quantity * ((float) ($item->discount_value ?? 0) / 100),
                default => 0.0,
            };

            return $discount;
        });
    }

    public function grandTotal(): float
    {
        return (float) $this->items->sum('line_total');
    }
}
