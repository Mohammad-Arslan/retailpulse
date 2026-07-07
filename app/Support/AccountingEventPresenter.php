<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccountingEvent;

final class AccountingEventPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(AccountingEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'source_type' => $event->source_type,
            'source_id' => $event->source_id,
            'processing_status' => $event->processing_status->value,
            'journal_entry' => $event->journalEntry ? [
                'id' => $event->journalEntry->id,
                'journal_number' => $event->journalEntry->journal_number,
                'status' => $event->journalEntry->status->value,
            ] : null,
            'error_message' => $event->error_message,
            'retry_count' => $event->retry_count,
            'processed_at' => $event->processed_at?->toIso8601String(),
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }
}
