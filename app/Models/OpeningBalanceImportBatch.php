<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountingImportBatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cutover_date',
    'file_name',
    'imported_by',
    'status',
    'validation_summary',
    'approved_by',
    'posted_journal_entry_id',
])]
class OpeningBalanceImportBatch extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cutover_date' => 'date',
            'status' => AccountingImportBatchStatus::class,
            'validation_summary' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OpeningBalanceImportLine::class);
    }

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postedJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'posted_journal_entry_id');
    }
}
