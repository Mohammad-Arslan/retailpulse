<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'customer_id', 'balance', 'expires_at', 'source_sale_id', 'notes'])]
class StoreCredit extends Model
{
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'source_sale_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StoreCreditTransaction::class);
    }
}
