<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'opening_balance_import_batch_id',
    'account_id',
    'debit',
    'credit',
    'party_type',
    'party_id',
    'warehouse_id',
    'product_variant_id',
    'cost_centre_id',
    'validation_status',
    'validation_message',
])]
class OpeningBalanceImportLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceImportBatch::class, 'opening_balance_import_batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
