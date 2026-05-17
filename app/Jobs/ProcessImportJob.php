<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportExport\ImportCompleted;
use App\Events\ImportExport\ImportProgressUpdated;
use App\Exceptions\ImportExport\ImportRowException;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\RowMapper;
use App\Services\ImportExport\SpreadsheetReader;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public int $jobId,
    ) {
        $this->onQueue('imports-heavy');
    }

    public function handle(DynamicRuleEngine $ruleEngine): void
    {
        $job = ImportExportJob::query()->findOrFail($this->jobId);
        $job->markProcessing();

        try {
            $handler = ImportExportRegistry::importHandler($job->entity_type);
            $context = ImportContext::fromJob($job);
            $reader = SpreadsheetReader::for((string) $job->input_file_path, 'import_export');
            $columnRules = $job->column_rules_snapshot ?? [];
            $mapping = $job->column_mapping ?? [];

            $errorIndexes = ImportRowError::query()
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

                foreach ($chunk as $rowIndex => $row) {
                    if (isset($errorIndexes[$rowIndex])) {
                        $chunkSkipped++;

                        continue;
                    }

                    $chunkProcessed++;
                    $transformed = $ruleEngine->applyTransforms($row, $columnRules);
                    $systemRow = RowMapper::toSystemKeys($transformed, $mapping);

                    try {
                        $result = $handler->processRow($systemRow, $context);

                        if ($result->success) {
                            $chunkSuccess++;
                        } else {
                            $chunkFailed++;
                            ImportRowError::query()->create([
                                'job_id' => $job->id,
                                'row_index' => $rowIndex,
                                'row_data' => $transformed,
                                'errors' => ['_row' => [$result->message ?? 'Processing failed']],
                            ]);
                        }
                    } catch (ImportRowException $e) {
                        $chunkFailed++;
                        ImportRowError::query()->create([
                            'job_id' => $job->id,
                            'row_index' => $rowIndex,
                            'row_data' => $transformed,
                            'errors' => ['_row' => [$e->getMessage()]],
                        ]);
                    }
                }

                $processed += $chunkProcessed;
                $success += $chunkSuccess;
                $failed += $chunkFailed;
                $skipped += $chunkSkipped;

                $job->incrementCounters($chunkProcessed, $chunkSuccess, $chunkFailed, $chunkSkipped);

                ImportProgressUpdated::dispatch($job->ulid, (int) $job->user_id, [
                    'phase' => 'processing',
                    'processed' => $processed,
                    'total' => $job->total_rows,
                    'success' => $success,
                    'failed' => $failed,
                    'skipped' => $skipped,
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
}
