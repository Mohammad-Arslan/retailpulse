<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalEntryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'journal_number',
    'journal_date',
    'fiscal_year_id',
    'legal_entity_id',
    'branch_id',
    'reference',
    'description',
    'source_module',
    'source_event',
    'source_reference_type',
    'source_reference_id',
    'source_number',
    'status',
    'is_system_generated',
    'is_opening_balance',
    'is_closing_entry',
    'reversal_of_journal_entry_id',
    'posted_at',
    'posted_by',
    'approved_by',
    'locked_at',
    'backdated_at',
    'backdated_reason',
    'created_by',
    'updated_by',
])]
class JournalEntry extends Model
{
    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'status' => JournalEntryStatus::class,
            'is_system_generated' => 'boolean',
            'is_opening_balance' => 'boolean',
            'is_closing_entry' => 'boolean',
            'posted_at' => 'datetime',
            'locked_at' => 'datetime',
            'backdated_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(JournalTransaction::class)->orderBy('line_sequence');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_journal_entry_id');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
