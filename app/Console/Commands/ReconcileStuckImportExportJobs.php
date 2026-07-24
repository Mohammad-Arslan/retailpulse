<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ReconcileStuckImportExportJobs extends Command
{
    protected $signature = 'import-export:reconcile-stuck
        {--minutes=30 : Mark jobs failed if stuck in a non-terminal status with no progress for this many minutes}
        {--dry-run : List stuck jobs without changing them}';

    protected $description = 'Fail import/export jobs abandoned by a dead worker (crash, OOM kill, ungraceful deploy) that never reached a terminal status';

    private const NON_TERMINAL_STATUSES = ['validating', 'processing', 'completing'];

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes($minutes);

        $jobs = ImportExportJob::query()
            ->whereIn('status', self::NON_TERMINAL_STATUSES)
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($jobs->isEmpty()) {
            $this->info("No stuck jobs found (no progress for {$minutes}+ minutes).");

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            $previousStatus = $job->status;
            $lastUpdatedAt = $job->updated_at?->toIso8601String();
            $message = "Reconciled: no progress for {$minutes}+ minutes while status was '{$previousStatus}'. ".
                'The worker likely died without running the job\'s failed() callback (OOM kill, server crash, or an ungraceful deploy).';

            $this->warn("Job #{$job->id} ({$job->ulid}, {$job->entity_type}, {$previousStatus}, last update {$lastUpdatedAt}) — ".
                ($dryRun ? 'would mark failed' : 'marking failed'));

            if ($dryRun) {
                continue;
            }

            $job->markFailed($message);

            Log::warning('import-export.reconcile-stuck: marked job failed', [
                'job_id' => $job->id,
                'ulid' => $job->ulid,
                'entity_type' => $job->entity_type,
                'previous_status' => $previousStatus,
                'last_updated_at' => $lastUpdatedAt,
            ]);
        }

        $this->info(($dryRun ? 'Would reconcile ' : 'Reconciled ').$jobs->count().' stuck job(s).');

        return self::SUCCESS;
    }
}
