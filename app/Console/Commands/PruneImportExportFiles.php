<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Traits\HandlesImportExportStorage;
use Illuminate\Console\Command;

final class PruneImportExportFiles extends Command
{
    use HandlesImportExportStorage;

    protected $signature = 'import-export:prune {--days=7 : Delete files for jobs completed more than this many days ago}';

    protected $description = 'Prune old import/export files and row error records';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $jobs = ImportExportJob::query()
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->where('completed_at', '<', $cutoff)
            ->get();

        $count = 0;

        foreach ($jobs as $job) {
            if ($job->ulid) {
                $this->cleanupJobFiles($job->ulid);
            }

            if ($job->input_file_path && $this->importFileExists($job->input_file_path)) {
                $this->deleteImportFile($job->input_file_path);
            }

            if ($job->output_file_path && $this->importFileExists($job->output_file_path)) {
                $this->deleteImportFile($job->output_file_path);
            }

            ImportRowError::query()->where('job_id', $job->id)->delete();
            $count++;
        }

        $this->info("Pruned {$count} import/export job(s).");

        return self::SUCCESS;
    }
}
