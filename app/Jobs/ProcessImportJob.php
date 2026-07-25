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

    public function __construct(
        public int $jobId,
    ) {
        $this->onQueue('imports-heavy');
    }

    public function handle(DynamicRuleEngine $ruleEngine): void
    {
        $lock = Cache::lock("import-job-processing:{$this->jobId}", $this->timeout + 60);

        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $this->processImport($ruleEngine);
        } finally {
            $lock->forceRelease();
        }
    }

    private function processImport(DynamicRuleEngine $ruleEngine): void
    {
        $job = ImportExportJob::query()->findOrFail($this->jobId);

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
                $newSuccessRows = [];
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
                    );

                    if ($outcome['ok']) {
                        $chunkSuccess++;
                        $newSuccessRows[] = [
                            'job_id' => $job->id,
                            'row_index' => $rowIndex,
                            'created_at' => now(),
                        ];
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

                if ($newSuccessRows !== []) {
                    ImportRowSuccess::query()->insert($newSuccessRows);
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

            $handler->afterImport($context);
            $job->refresh();

            if (ImportRowError::query()->where('job_id', $job->id)->exists()) {
                GenerateErrorReportJob::dispatch($job->id)->onQueue('imports-reports');

                return;
            }

            $job->markCompleted();
            $job->update(['summary' => $job->buildSummary()]);

            ImportCompleted::dispatch($job->ulid, (int) $job->user_id, $job->buildSummary());
        } catch (Throwable $e) {
            $job->markFailed($e->getMessage());
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
     * @param  array<string, mixed>  $systemRow
     * @return array{ok: bool, errors?: array<string, list<string>>}
     */
    private function processRowWithIsolation(
        ImportHandler $handler,
        array $systemRow,
        ImportContext $context,
        ImportErrorFormatter $formatter,
    ): array {
        $attempts = 0;

        while (true) {
            try {
                /** @var ImportRowResult $result */
                $result = DB::transaction(
                    fn () => $handler->processRow($systemRow, $context)
                );

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
