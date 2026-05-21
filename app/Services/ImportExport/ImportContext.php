<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Models\ImportExportJob;

final readonly class ImportContext
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $jobId,
        public int $tenantId,
        public int $userId,
        public string $mode,
        public bool $isDryRun,
        public string $filePath,
        public string $disk,
        public array $options,
    ) {}

    public static function fromJob(ImportExportJob $job): self
    {
        return new self(
            jobId: (int) $job->id,
            tenantId: (int) $job->tenant_id,
            userId: (int) $job->user_id,
            mode: (string) ($job->mode ?? 'create'),
            isDryRun: (bool) $job->is_dry_run,
            filePath: (string) $job->input_file_path,
            disk: (string) $job->disk,
            options: $job->options ?? [],
        );
    }

    public function isStrictMode(): bool
    {
        return (bool) ($this->options['strict'] ?? false);
    }

    public function shouldSkipInvalidRows(): bool
    {
        if ($this->isStrictMode()) {
            return false;
        }

        return ($this->options['skip_invalid_rows'] ?? true) !== false;
    }
}
