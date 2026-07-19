<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountingEventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'event_type',
    'source_type',
    'source_id',
    'idempotency_key',
    'processing_status',
    'journal_entry_id',
    'error_message',
    'retry_count',
    'payload',
    'processed_at',
])]
class AccountingEvent extends Model
{
    protected function casts(): array
    {
        return [
            'processing_status' => AccountingEventStatus::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
