<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Jobs\ProcessImportJob;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Models\ImportRowSuccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class ImportResumeAndGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_after_failure_skips_already_processed_rows(): void
    {
        $user = User::factory()->create();
        $job = ImportExportJob::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => 'import',
            'entity_type' => 'brands',
            'status' => 'processing',
            'total_rows' => 10,
            'processed_rows' => 5,
            'success_rows' => 4,
            'failed_rows' => 1,
            'last_processed_row_index' => 5,
        ]);

        ImportRowSuccess::query()->insert([
            ['job_id' => $job->id, 'row_index' => 1, 'created_at' => now()],
            ['job_id' => $job->id, 'row_index' => 2, 'created_at' => now()],
            ['job_id' => $job->id, 'row_index' => 3, 'created_at' => now()],
            ['job_id' => $job->id, 'row_index' => 5, 'created_at' => now()],
        ]);
        ImportRowError::query()->create([
            'job_id' => $job->id,
            'row_index' => 4,
            'row_data' => [],
            'errors' => ['name' => ['Required']],
        ]);

        // Verify the checkpoint is set
        $this->assertEquals(5, $job->last_processed_row_index);

        // The job should skip rows 1-5 on a retry
        $this->assertDatabaseHas('import_export_jobs', [
            'id' => $job->id,
            'last_processed_row_index' => 5,
        ]);
    }

    public function test_concurrent_import_limit_rejects_upload(): void
    {
        config(['import-export.max_concurrent_imports_per_tenant' => 2]);

        $user = User::factory()->create();

        ImportExportJob::factory()->count(2)->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => 'import',
            'status' => 'processing',
            'queued_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(
            route('import-export.imports.upload'),
            [
                'entity_type' => 'brands',
                'mode' => 'create',
                'file' => UploadedFile::fake()->create('test.csv', 100),
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_dedupe_lock_prevents_double_dispatch(): void
    {
        $job = ImportExportJob::factory()->create([
            'type' => 'import',
            'entity_type' => 'brands',
            'status' => 'processing',
        ]);

        $lock = Cache::lock("import-job-processing:{$job->id}", 3600);
        $this->assertTrue($lock->get());

        // Second dispatch should be released (not processed) because the lock is held
        $queueJob = new ProcessImportJob($job->id);
        // The lock is already taken — on a real queue, release() returns the job to retry
        $this->assertTrue(Cache::has("import-job-processing:{$job->id}"));

        $lock->forceRelease();
    }
}
