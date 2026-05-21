<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Models\ImportExportJob;
use App\Support\TenantImportScope;

final readonly class ExportContext
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $jobId,
        public ?int $tenantId,
        public int $userId,
        public array $options,
    ) {}

    public static function fromJob(ImportExportJob $job): self
    {
        return new self(
            jobId: (int) $job->id,
            tenantId: TenantImportScope::normalize($job->tenant_id),
            userId: (int) $job->user_id,
            options: $job->options ?? [],
        );
    }
}
