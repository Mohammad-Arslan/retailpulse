<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountingImportBatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'file_name',
    'imported_by',
    'status',
    'validation_summary',
    'approved_by',
])]
class CoaImportBatch extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => AccountingImportBatchStatus::class,
            'validation_summary' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
