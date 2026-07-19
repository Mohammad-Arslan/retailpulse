<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'sale_invoice_id',
    'attempts',
    'last_attempted_at',
    'next_attempt_at',
    'last_error',
    'status',
])]
class FbrInvoiceQueue extends Model
{
    protected $table = 'fbr_invoice_queue';

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempted_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }

    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class);
    }
}
