<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OpeningBalanceReconciliationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'opening_balance_import_batch_id',
    'reconciliation_type',
    'source_total',
    'import_total',
    'variance',
    'status',
    'variance_approved_by',
])]
class OpeningBalanceReconciliation extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'source_total' => 'decimal:2',
            'import_total' => 'decimal:2',
            'variance' => 'decimal:2',
            'status' => OpeningBalanceReconciliationStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceImportBatch::class, 'opening_balance_import_batch_id');
    }

    public function varianceApprovedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'variance_approved_by');
    }
}
