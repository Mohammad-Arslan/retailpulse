<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'coa_import_batch_id',
    'code',
    'name',
    'type',
    'parent_code',
    'is_group',
    'is_postable',
    'branch_code',
    'currency_code',
    'status',
    'validation_status',
    'validation_message',
])]
class CoaImportLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'is_postable' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CoaImportBatch::class, 'coa_import_batch_id');
    }
}
