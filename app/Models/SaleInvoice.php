<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FbrInvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sale_id',
    'number',
    'template',
    'pdf_path',
    'public_token',
    'fbr_status',
    'fbr_invoice_number',
    'emailed_at',
])]
class SaleInvoice extends Model
{
    protected function casts(): array
    {
        return [
            'fbr_status' => FbrInvoiceStatus::class,
            'emailed_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function fbrQueueEntry(): HasOne
    {
        return $this->hasOne(FbrInvoiceQueue::class);
    }
}
