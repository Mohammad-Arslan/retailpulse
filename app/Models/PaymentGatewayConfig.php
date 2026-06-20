<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'gateway',
    'mode',
    'credentials',
    'priority',
])]
class PaymentGatewayConfig extends Model
{
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'priority' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
