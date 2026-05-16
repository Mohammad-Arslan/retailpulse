<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportExport\ImportProgressUpdated;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\SpreadsheetReader;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ValidateImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $jobId,
    ) {
        $this->onQueue('imports-validation');
    }

    public function handle(DynamicRuleEngine $ruleEngine): void
    {
        $job = ImportExportJob::query()->findOrFail($this->jobId);
        $job->markValidating();

        try {
            $handler = ImportExportRegistry::importHandler($job->entity_type);
            $context = ImportContext::fromJob($job);
            $reader = SpreadsheetReader::for((string) $job->input_file_path, 'import_export');
            $totalRows = $reader->count();
            $job->update(['total_rows' => $totalRows]);

            $columnRules = $job->column_rules_snapshot ?? [];
            $processed = 0;
            $errorCount = 0;
            $batch = [];

            foreach ($reader->lazyRows() as $rowIndex => $row) {
                $processed++;
                $transformed = $ruleEngine->applyTransforms($row, $columnRules);
                $errors = $handler->validateRow($transformed, $context);

                if ($errors !== []) {
                    $errorCount++;
                    $batch[] = [
                        'job_id' => $job->id,
                        'row_index' => $rowIndex,
                        'row_data' => json_encode($transformed),
                        'errors' => json_encode($errors),
                        'created_at' => now(),
                    ];
                }

                if (count($batch) >= 500) {
                    ImportRowError::query()->insert($batch);
                    $batch = [];
                }

                if ($processed % 100 === 0) {
                    $this->broadcastProgress($job, 'validating', $processed, $totalRows, $errorCount);
                }
            }

            if ($batch !== []) {
                ImportRowError::query()->insert($batch);
            }

            $hasErrors = ImportRowError::query()->where('job_id', $job->id)->exists();
            $job->markValidated();

            if ($job->is_dry_run || ($hasErrors && $context->isStrictMode())) {
                GenerateErrorReportJob::dispatch($job->id)->onQueue('imports-reports');

                return;
            }

            ProcessImportJob::dispatch($job->id)->onQueue('imports-heavy');
        } catch (Throwable $e) {
            $job->markFailed($e->getMessage());
            throw $e;
        }
    }

    private function broadcastProgress(
        ImportExportJob $job,
        string $phase,
        int $processed,
        int $total,
        int $errorCount,
    ): void {
        ImportProgressUpdated::dispatch($job->ulid, (int) $job->user_id, [
            'phase' => $phase,
            'processed' => $processed,
            'total' => $total,
            'errors' => $errorCount,
        ]);
    }
}
