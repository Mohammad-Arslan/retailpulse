<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_type',
    'branch_id',
    'legal_entity_id',
    'prefix',
    'next_number',
    'reset_frequency',
    'fiscal_year_id',
    'status',
])]
class DocumentSequence extends Model
{
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
