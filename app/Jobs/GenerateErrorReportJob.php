<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportExport\ImportCompleted;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Services\ImportExport\ImportErrorFormatter;
use App\Traits\HandlesImportExportStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Rap2hpoutre\FastExcel\FastExcel;
use Throwable;

final class GenerateErrorReportJob implements ShouldQueue
{
    use Dispatchable, HandlesImportExportStorage, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $jobId,
    ) {
        $this->onQueue('imports-reports');
    }

    public function handle(): void
    {
        $job = ImportExportJob::query()->findOrFail($this->jobId);

        try {
            $formatter = ImportErrorFormatter::forJob($job);

            $rows = ImportRowError::query()
                ->where('job_id', $job->id)
                ->orderBy('row_index')
                ->get()
                ->flatMap(function (ImportRowError $error) use ($formatter) {
                    $lines = [];
                    $rowData = is_array($error->row_data) ? $error->row_data : [];

                    foreach ($error->errors as $field => $messages) {
                        foreach ((array) $messages as $message) {
                            $lines[] = [
                                'Row Number' => $error->row_index,
                                'Field' => $field === '_row' ? '—' : $formatter->fieldLabel((string) $field),
                                'Error' => $formatter->formatMessage(
                                    (string) $field,
                                    (string) $message,
                                    $rowData[$field] ?? null,
                                ),
                                'Original Value' => is_scalar($rowData[$field] ?? null)
                                    ? (string) ($rowData[$field] ?? '')
                                    : json_encode($rowData[$field] ?? $rowData),
                            ];
                        }
                    }

                    return $lines;
                })
                ->all();

            $tmp = tempnam(sys_get_temp_dir(), 'error_report_').'.xlsx';
            (new FastExcel($rows))->export($tmp);
            $content = file_get_contents($tmp) ?: '';
            @unlink($tmp);

            $path = $this->storeErrorReport($content, $job->ulid);

            $errorCount = (int) ImportRowError::query()->where('job_id', $job->id)->count();

            $job->update([
                'output_file_path' => $path,
                'processed_rows' => $job->total_rows,
                'failed_rows' => max($job->failed_rows, $errorCount),
                'skipped_rows' => max($job->skipped_rows, $errorCount),
            ]);

            $job->markCompleted();

            $summary = $job->buildSummary();
            $job->update(['summary' => $summary]);

            ImportCompleted::dispatch($job->ulid, (int) $job->user_id, $summary);
        } catch (Throwable $e) {
            $job->markFailed($e->getMessage());
            throw $e;
        }
    }
}
