<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountingEventStatus;
use App\Models\AccountingEvent;
use Illuminate\Database\UniqueConstraintViolationException;
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
        ?string $idempotencySuffix = null,
    ): AccountingEvent {
        $idempotencyKey = $this->idempotencyKey($eventType, $sourceType, $sourceId, $idempotencySuffix);

        $existing = $this->findForSource($eventType, $sourceType, $sourceId, $idempotencySuffix);

        if ($existing?->processing_status === AccountingEventStatus::Completed) {
            return $existing;
        }

        if ($existing?->processing_status === AccountingEventStatus::Skipped) {
            return $existing;
        }

        $event = $existing ?? $this->createOrFetchExisting(
            $eventType,
            $sourceType,
            $sourceId,
            $idempotencyKey,
            $payload,
        );

        $event = $this->recoverStaleProcessing($event);

        if ($event->processing_status === AccountingEventStatus::Completed) {
            return $event;
        }

        if ($this->postingRuleEngine->findRuleSet($eventType, $payload) === null) {
            $event->update([
                'processing_status' => AccountingEventStatus::Skipped,
                'payload' => $payload,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return $event->fresh(['journalEntry']);
        }

        $event->update([
            'processing_status' => AccountingEventStatus::Processing,
            'payload' => $payload,
        ]);

        try {
            $journal = DB::transaction(function () use ($event, $eventType, $payload, $sourceType, $sourceId, $userId) {
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

                $posted = $this->journalService->post($entry, $userId > 0 ? $userId : ($payload['user_id'] ?? 1));

                $event->update([
                    'processing_status' => AccountingEventStatus::Completed,
                    'journal_entry_id' => $posted->id,
                    'processed_at' => now(),
                    'error_message' => null,
                ]);

                return $posted;
            });
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
        if (! in_array($event->processing_status, [AccountingEventStatus::Failed, AccountingEventStatus::Processing], true)) {
            return $event;
        }

        if ($event->processing_status === AccountingEventStatus::Processing) {
            $event = $this->recoverStaleProcessing($event);

            if ($event->processing_status === AccountingEventStatus::Completed) {
                return $event;
            }

            // Still in-flight within the stale window — do not create a second journal.
            if ($event->processing_status === AccountingEventStatus::Processing) {
                return $event;
            }
        }

        return $this->process(
            $event->event_type,
            $event->source_type,
            (int) $event->source_id,
            $event->payload ?? [],
            $userId,
            $this->suffixFromIdempotencyKey(
                $event->idempotency_key,
                $event->event_type,
                $event->source_type,
                (int) $event->source_id,
            ),
        );
    }

    public function findForSource(
        string $eventType,
        string $sourceType,
        int $sourceId,
        ?string $idempotencySuffix = null,
    ): ?AccountingEvent {
        return AccountingEvent::query()
            ->where('idempotency_key', $this->idempotencyKey($eventType, $sourceType, $sourceId, $idempotencySuffix))
            ->first();
    }

    public function idempotencyKey(
        string $eventType,
        string $sourceType,
        int $sourceId,
        ?string $idempotencySuffix = null,
    ): string {
        $key = "{$eventType}:{$sourceType}:{$sourceId}";

        if ($idempotencySuffix !== null && $idempotencySuffix !== '') {
            $key .= ':'.$idempotencySuffix;
        }

        return $key;
    }

    private function suffixFromIdempotencyKey(
        string $idempotencyKey,
        string $eventType,
        string $sourceType,
        int $sourceId,
    ): ?string {
        $prefix = "{$eventType}:{$sourceType}:{$sourceId}";

        if ($idempotencyKey === $prefix) {
            return null;
        }

        if (! str_starts_with($idempotencyKey, $prefix.':')) {
            return null;
        }

        return substr($idempotencyKey, strlen($prefix) + 1) ?: null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createOrFetchExisting(
        string $eventType,
        string $sourceType,
        int $sourceId,
        string $idempotencyKey,
        array $payload,
    ): AccountingEvent {
        try {
            return AccountingEvent::query()->create([
                'event_type' => $eventType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'idempotency_key' => $idempotencyKey,
                'processing_status' => AccountingEventStatus::Pending,
                'payload' => $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            return AccountingEvent::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }
    }

    private function recoverStaleProcessing(AccountingEvent $event): AccountingEvent
    {
        if ($event->processing_status !== AccountingEventStatus::Processing) {
            return $event;
        }

        if ($event->journal_entry_id !== null) {
            $event->update([
                'processing_status' => AccountingEventStatus::Completed,
                'processed_at' => $event->processed_at ?? now(),
                'error_message' => null,
            ]);

            return $event->fresh(['journalEntry']);
        }

        $staleAfter = max(60, (int) config('accounting.processing_stale_after_seconds', 300));

        if ($event->updated_at !== null && $event->updated_at->diffInSeconds(now()) < $staleAfter) {
            return $event;
        }

        $event->update([
            'processing_status' => AccountingEventStatus::Failed,
            'error_message' => 'Processing timed out before completion.',
            'retry_count' => $event->retry_count + 1,
        ]);

        return $event->fresh(['journalEntry']);
    }
}
