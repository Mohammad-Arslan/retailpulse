<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyPointType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'sale_id', 'points', 'type', 'description'])]
class LoyaltyPoint extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => LoyaltyPointType::class,
            'points' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
