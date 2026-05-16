<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportExport\ExportCompleted;
use App\Events\ImportExport\ImportProgressUpdated;
use App\Models\ImportExportJob;
use App\Services\ImportExport\ExportContext;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Rap2hpoutre\FastExcel\FastExcel;
use Throwable;

final class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public int $jobId,
    ) {
        $this->onQueue('exports');
    }

    public function handle(ImportExportStorageManager $storage): void
    {
        $job = ImportExportJob::query()->findOrFail($this->jobId);
        $job->markProcessing();

        try {
            $handler = ImportExportRegistry::exportHandler($job->entity_type);
            $context = ExportContext::fromJob($job);
            $query = $handler->query($context);

            $generator = function () use ($handler, $context, $query, $job): Generator {
                $processed = 0;
                $cursor = $query instanceof \Illuminate\Database\Eloquent\Builder
                    ? $query->cursor()
                    : $query;

                foreach ($cursor as $record) {
                    yield $handler->map($record, $context);
                    $processed++;

                    if ($processed % 500 === 0) {
                        $job->update(['processed_rows' => $processed]);
                        ImportProgressUpdated::dispatch($job->ulid, (int) $job->user_id, [
                            'phase' => 'exporting',
                            'processed' => $processed,
                            'total' => $processed,
                        ]);
                    }
                }

                $job->update(['total_rows' => $processed, 'processed_rows' => $processed]);
            };

            $tmp = tempnam(sys_get_temp_dir(), 'export_').'.xlsx';
            (new FastExcel($generator()))->export($tmp);

            $path = sprintf(
                'exports/%s/%s/%s/%s.xlsx',
                $job->entity_type,
                now()->format('Y'),
                now()->format('m'),
                $job->ulid,
            );
            $storage->storeContent(file_get_contents($tmp) ?: '', $path);
            @unlink($tmp);

            $job->update(['output_file_path' => $path]);
            $job->markCompleted();

            $downloadUrl = $storage->temporaryUrl($path);

            ExportCompleted::dispatch($job->ulid, (int) $job->user_id, [
                'download_url' => $downloadUrl,
                'row_count' => $job->processed_rows,
                'job_ulid' => $job->ulid,
            ]);
        } catch (Throwable $e) {
            $job->markFailed($e->getMessage());
            throw $e;
        }
    }
}
