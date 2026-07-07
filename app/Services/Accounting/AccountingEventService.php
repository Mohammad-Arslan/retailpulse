<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountingEventStatus;
use App\Models\AccountingEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AccountingEventService
{
    public function __construct(
        private readonly PostingRuleEngine $postingRuleEngine,
        private readonly JournalService $journalService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(
        string $eventType,
        string $sourceType,
        int $sourceId,
        array $payload,
        int $userId = 0,
    ): AccountingEvent {
        $idempotencyKey = "{$eventType}:{$sourceType}:{$sourceId}";

        $existing = AccountingEvent::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing?->processing_status === AccountingEventStatus::Completed) {
            return $existing;
        }

        $event = $existing ?? AccountingEvent::query()->create([
            'event_type' => $eventType,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'idempotency_key' => $idempotencyKey,
            'processing_status' => AccountingEventStatus::Pending,
            'payload' => $payload,
        ]);

        $event->update([
            'processing_status' => AccountingEventStatus::Processing,
            'payload' => $payload,
        ]);

        try {
            $journal = DB::transaction(function () use ($eventType, $payload, $sourceType, $sourceId, $userId) {
                $lines = $this->postingRuleEngine->buildJournalLines($eventType, $payload);

                $entry = $this->journalService->createDraft([
                    'journal_date' => $payload['date'] ?? now()->toDateString(),
                    'branch_id' => $payload['branch_id'] ?? null,
                    'legal_entity_id' => $payload['legal_entity_id'] ?? null,
                    'description' => $payload['description'] ?? $eventType,
                    'source_module' => $payload['source_module'] ?? null,
                    'source_event' => $eventType,
                    'source_reference_type' => $sourceType,
                    'source_reference_id' => $sourceId,
                    'source_number' => $payload['source_number'] ?? null,
                    'is_system_generated' => true,
                ], $lines, $userId > 0 ? $userId : ($payload['user_id'] ?? 1));

                return $this->journalService->post($entry, $userId > 0 ? $userId : ($payload['user_id'] ?? 1));
            });

            $event->update([
                'processing_status' => AccountingEventStatus::Completed,
                'journal_entry_id' => $journal->id,
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $event->update([
                'processing_status' => AccountingEventStatus::Failed,
                'error_message' => $e->getMessage(),
                'retry_count' => $event->retry_count + 1,
            ]);

            throw $e;
        }

        return $event->fresh(['journalEntry']);
    }

    public function retry(AccountingEvent $event, int $userId): AccountingEvent
    {
        if ($event->processing_status !== AccountingEventStatus::Failed) {
            return $event;
        }

        return $this->process(
            $event->event_type,
            $event->source_type,
            (int) $event->source_id,
            $event->payload ?? [],
            $userId,
        );
    }
}
