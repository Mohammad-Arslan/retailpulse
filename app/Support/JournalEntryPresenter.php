<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\JournalEntry;
use App\Models\JournalTransaction;

final class JournalEntryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'journal_number' => $entry->journal_number,
            'journal_date' => $entry->journal_date?->toDateString(),
            'reference' => $entry->reference,
            'description' => $entry->description,
            'status' => $entry->status->value,
            'branch_id' => $entry->branch_id,
            'branch' => $entry->branch ? [
                'id' => $entry->branch->id,
                'name' => $entry->branch->name,
            ] : null,
            'is_system_generated' => $entry->is_system_generated,
            'posted_at' => $entry->posted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forDetail(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'journal_number' => $entry->journal_number,
            'journal_date' => $entry->journal_date?->toDateString(),
            'reference' => $entry->reference,
            'description' => $entry->description,
            'status' => $entry->status->value,
            'branch_id' => $entry->branch_id,
            'branch_name' => $entry->branch?->name,
            'fiscal_year_id' => $entry->fiscal_year_id,
            'fiscal_year_name' => $entry->fiscalYear?->name,
            'is_system_generated' => $entry->is_system_generated,
            'is_opening_balance' => $entry->is_opening_balance,
            'is_closing_entry' => $entry->is_closing_entry,
            'source_module' => $entry->source_module,
            'source_event' => $entry->source_event,
            'source_number' => $entry->source_number,
            'posted_at' => $entry->posted_at?->toIso8601String(),
            'posted_by_name' => $entry->postedByUser?->name,
            'reversal_of' => $entry->reversalOf ? [
                'id' => $entry->reversalOf->id,
                'journal_number' => $entry->reversalOf->journal_number,
            ] : null,
            'transactions' => $entry->transactions->map(fn (JournalTransaction $line) => [
                'id' => $line->id,
                'line_sequence' => $line->line_sequence,
                'account_id' => $line->account_id,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'debit' => number_format((float) $line->debit, 2, '.', ''),
                'credit' => number_format((float) $line->credit, 2, '.', ''),
                'currency_code' => $line->currency_code,
                'description' => $line->description,
            ]),
            'total_debit' => number_format((float) $entry->transactions->sum('debit'), 2, '.', ''),
            'total_credit' => number_format((float) $entry->transactions->sum('credit'), 2, '.', ''),
        ];
    }
}
