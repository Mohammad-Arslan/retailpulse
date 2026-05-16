<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportExport\ImportCompleted;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
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
            $rows = ImportRowError::query()
                ->where('job_id', $job->id)
                ->orderBy('row_index')
                ->get()
                ->flatMap(function (ImportRowError $error) {
                    $lines = [];
                    foreach ($error->errors as $field => $messages) {
                        foreach ((array) $messages as $message) {
                            $lines[] = [
                                'Row Number' => $error->row_index,
                                'Field' => $field,
                                'Error' => $message,
                                'Original Value' => json_encode($error->row_data[$field] ?? $error->row_data),
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
            $job->update(['output_file_path' => $path]);

            $summary = $job->buildSummary();
            ImportCompleted::dispatch($job->ulid, (int) $job->user_id, $summary);
        } catch (Throwable $e) {
            $job->markFailed($e->getMessage());
            throw $e;
        }
    }
}
