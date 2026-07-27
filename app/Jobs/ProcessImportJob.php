<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportExport\ImportCompleted;
use App\Events\ImportExport\ImportProgressUpdated;
use App\Exceptions\ImportExport\ImportRowException;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Models\ImportRowSuccess;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportErrorFormatter;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\ImportQueryExceptionClassifier;
use App\Services\ImportExport\ImportRowResult;
use App\Services\ImportExport\RowMapper;
use App\Services\ImportExport\SpreadsheetReader;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    /** @var list<int> */
    public array $backoff = [30, 120];

    private const int ROW_TRANSIENT_RETRIES = 3;

    private const int PROGRESS_BROADCAST_INTERVAL_SECONDS = 3;

    private float $lastProgressBroadcastAt = 0;

    public function __construct(
        public int $jobId,
    ) {
        $this->onQueue('imports-heavy');
    }

    /**
     * The advisory lock key for "this uploaded file, for this entity type and
     * tenant, is currently being processed". Same file cannot run on two
     * workers at once; different files for the same entity_type may still run
     * concurrently. Shared between the job and its tests so they cannot drift.
     */
    public static function entityLockKey(ImportExportJob $job): string
    {
        return sprintf(
            'import-entity:%s:%s:%s',
            $job->entity_type,
            (string) $job->tenant_id,
            sha1((string) $job->input_file_path),
        );
    }

    public function handle(DynamicRuleEngine $ruleEngine): void
    {
        $lock = Cache::lock("import-job-processing:{$this->jobId}", $this->timeout + 60);

        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $job = ImportExportJob::query()->findOrFail($this->jobId);

            // Entity+file advisory lock: same uploaded file cannot run on two workers.
            // Different files for the same entity_type may still run concurrently.
            $entityLock = Cache::lock(self::entityLockKey($job), $this->timeout + 60);

            if (! $entityLock->get()) {
                $lock->forceRelease();
                $this->release(15);

                return;
            }

            try {
                $this->processImport($ruleEngine, $job);
            } finally {
                $entityLock->forceRelease();
            }
        } finally {
            $lock->forceRelease();
        }
    }

    private function processImport(DynamicRuleEngine $ruleEngine, ImportExportJob $job): void
    {

        if ($job->status !== 'processing') {
            $job->markProcessing();
        }

        try {
            $handler = ImportExportRegistry::importHandler($job->entity_type);
            $context = ImportContext::fromJob($job);
            $reader = SpreadsheetReader::for((string) $job->input_file_path, 'import_export');
            $columnRules = $job->column_rules_snapshot ?? [];
            $mapping = $job->column_mapping ?? [];
            $formatter = ImportErrorFormatter::forJob($job);

            $checkpoint = $job->last_processed_row_index;

            $errorIndexes = ImportRowError::query()
                ->where('job_id', $job->id)
                ->pluck('row_index')
                ->flip()
                ->all();

            $successIndexes = ImportRowSuccess::query()
                ->where('job_id', $job->id)
                ->pluck('row_index')
                ->flip()
                ->all();

            $chunkSize = $handler->chunkSize();
            $processed = 0;
            $success = 0;
            $failed = 0;
            $skipped = 0;

            foreach ($reader->chunkRows($chunkSize) as $chunk) {
                $chunkProcessed = 0;
                $chunkSuccess = 0;
                $chunkFailed = 0;
                $chunkSkipped = 0;
                $chunkMaxRowIndex = null;

                foreach ($chunk as $rowIndex => $row) {
                    if ($checkpoint !== null && $rowIndex <= $checkpoint) {
                        continue;
                    }

                    if (isset($errorIndexes[$rowIndex])) {
                        $chunkSkipped++;

                        continue;
                    }

                    if (isset($successIndexes[$rowIndex])) {
                        $chunkSuccess++;

                        continue;
                    }

                    $chunkProcessed++;
                    $transformed = $ruleEngine->applyTransforms($row, $columnRules);
                    $systemRow = RowMapper::toSystemKeys($transformed, $mapping);

                    $outcome = $this->processRowWithIsolation(
                        $handler,
                        $systemRow,
                        $context,
                        $formatter,
                        $job->id,
                        $rowIndex,
                    );

                    if ($outcome['ok']) {
                        $chunkSuccess++;
                    } else {
                        $chunkFailed++;
                        ImportRowError::query()->create([
                            'job_id' => $job->id,
                            'row_index' => $rowIndex,
                            'row_data' => $transformed,
                            'errors' => $outcome['errors'],
                        ]);
                    }

                    $chunkMaxRowIndex = $rowIndex;
                }

                $processed += $chunkProcessed;
                $success += $chunkSuccess;
                $failed += $chunkFailed;
                $skipped += $chunkSkipped;

                $job->incrementCounters($chunkProcessed, $chunkSuccess, $chunkFailed, $chunkSkipped);

                if ($chunkMaxRowIndex !== null) {
                    ImportExportJob::query()
                        ->whereKey($job->id)
                        ->update(['last_processed_row_index' => $chunkMaxRowIndex]);
                }

                $job->refresh();

                $now = microtime(true);
                if (($now - $this->lastProgressBroadcastAt) >= self::PROGRESS_BROADCAST_INTERVAL_SECONDS) {
                    $this->lastProgressBroadcastAt = $now;
                    ImportProgressUpdated::dispatch($job->ulid, (int) $job->user_id, [
                        'phase' => 'processing',
                        'processed' => (int) $job->processed_rows,
                        'total' => (int) $job->total_rows,
                        'success' => (int) $job->success_rows,
                        'failed' => (int) $job->failed_rows,
                        'skipped' => (int) $job->skipped_rows,
                        'errors' => (int) $job->failed_rows,
                    ]);
                }
            }

            // No trailing progress broadcast here: ImportCompleted (dispatched
            // below) already carries the final counts via buildSummary(), and
            // GenerateErrorReportJob dispatches its own ImportCompleted when
            // there are row errors. Emitting one more `.progress.updated` at
            // this point would risk being delivered after the completed event,
            // which the client must never allow to un-complete a finished job —
            // simplest is to not send a redundant one at all.

            // Only run afterImport if at least one row succeeded; handlers may
            // finalize batch state (e.g. OpeningBalanceImportHandler::finalize)
            // that would be meaningless or harmful on a fully-failed import.
            if ((int) $job->success_rows > 0) {
                $handler->afterImport($context);
            }

            $job->refresh();

            if (ImportRowError::query()->where('job_id', $job->id)->exists()) {
                GenerateErrorReportJob::dispatch($job->id)->onQueue('imports-reports');

                return;
            }

            $job->markCompleted();
            $job->update(['summary' => $job->buildSummary()]);

            ImportCompleted::dispatch($job->ulid, (int) $job->user_id, $job->buildSummary());
        } catch (Throwable $e) {
            // Do not markFailed here — retries remain. Terminal failure is handled in failed().
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $job = ImportExportJob::query()->find($this->jobId);

        if ($job !== null && ! in_array($job->status, ['completed', 'failed'], true)) {
            $job->markFailed($exception->getMessage());
        }

        Cache::lock("import-job-processing:{$this->jobId}")->forceRelease();
    }

    /**
     * Process a single row inside its own transaction/savepoint. Integrity and exhausted
     * transient DB errors become row failures; systemic QueryExceptions rethrow.
     *
     * The ImportRowSuccess marker is written in the *same* transaction as the row's own
     * business writes, not batched at the end of the chunk. Processing is at-least-once
     * per row (a worker can die mid-chunk and the row gets replayed on retry) — some
     * handlers apply deltas rather than upserting on a natural key (e.g.
     * InventoryAdjustmentImportHandler's stock adjustments aren't safe to reapply), so the
     * success marker must be durable at the exact moment the row's effects are, or a
     * replay can't tell "already applied" from "never attempted".
     *
     * @param  array<string, mixed>  $systemRow
     * @return array{ok: bool, errors?: array<string, list<string>>}
     */
    private function processRowWithIsolation(
        ImportHandler $handler,
        array $systemRow,
        ImportContext $context,
        ImportErrorFormatter $formatter,
        int $jobId,
        int $rowIndex,
    ): array {
        $attempts = 0;

        while (true) {
            try {
                /** @var ImportRowResult $result */
                $result = DB::transaction(function () use ($handler, $systemRow, $context, $jobId, $rowIndex) {
                    $result = $handler->processRow($systemRow, $context);

                    if ($result->success) {
                        ImportRowSuccess::query()->create([
                            'job_id' => $jobId,
                            'row_index' => $rowIndex,
                        ]);
                    }

                    return $result;
                });

                if ($result->success) {
                    return ['ok' => true];
                }

                return [
                    'ok' => false,
                    'errors' => ['_row' => [$result->message ?? 'Processing failed']],
                ];
            } catch (ImportRowException $e) {
                return [
                    'ok' => false,
                    'errors' => ['_row' => [$e->getMessage()]],
                ];
            } catch (QueryException $e) {
                $kind = ImportQueryExceptionClassifier::classify($e);

                if ($kind === ImportQueryExceptionClassifier::KIND_TRANSIENT && $attempts < self::ROW_TRANSIENT_RETRIES) {
                    $attempts++;
                    usleep(50_000 * $attempts);

                    continue;
                }

                if (
                    $kind === ImportQueryExceptionClassifier::KIND_INTEGRITY
                    || $kind === ImportQueryExceptionClassifier::KIND_TRANSIENT
                ) {
                    return [
                        'ok' => false,
                        'errors' => $formatter->fromQueryException($e),
                    ];
                }

                throw $e;
            }
        }
    }
}
