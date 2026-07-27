<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Jobs\GenerateErrorReportJob;
use App\Jobs\ProcessImportJob;
use App\Models\Brand;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Models\ImportRowSuccess;
use App\Models\User;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\ImportExport\BlindBrandInsertExportHandler;
use Tests\Support\ImportExport\BlindBrandInsertImportHandler;
use Tests\TestCase;

final class ProcessImportJobRowIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        BlindBrandInsertImportHandler::reset();
        ImportExportRegistry::register(
            'test-blind-brands',
            BlindBrandInsertImportHandler::class,
            BlindBrandInsertExportHandler::class,
        );

        $this->user = User::factory()->create(['is_active' => true]);
    }

    public function test_integrity_violation_on_one_row_does_not_fail_the_job(): void
    {
        Queue::fake([GenerateErrorReportJob::class]);

        Brand::query()->create([
            'tenant_id' => null,
            'slug' => 'dup-code',
            'name' => 'Existing',
            'is_active' => true,
        ]);

        $job = $this->createJob($this->csv([
            ['code' => 'dup-code', 'name' => 'Clash'],
            ['code' => 'ok-brand', 'name' => 'OK Brand'],
        ]));

        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertNotSame('failed', $job->status);
        $this->assertSame(1, Brand::query()->where('slug', 'ok-brand')->count());
        $this->assertSame(1, ImportRowError::query()->where('job_id', $job->id)->count());

        $error = ImportRowError::query()->where('job_id', $job->id)->first();
        $flat = json_encode($error->errors);
        $this->assertStringContainsString('Duplicate value — must be unique', (string) $flat);
        $this->assertStringNotContainsString('brands', (string) $flat);
        $this->assertStringNotContainsString('brands_slug_unique', (string) $flat);

        Queue::assertPushed(GenerateErrorReportJob::class);
    }

    public function test_same_file_duplicate_unique_key_second_row_errors(): void
    {
        Queue::fake([GenerateErrorReportJob::class]);

        $job = $this->createJob($this->csv([
            ['code' => 'same-code', 'name' => 'First'],
            ['code' => 'same-code', 'name' => 'Second'],
            ['code' => 'other-code', 'name' => 'Third'],
        ]));

        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertNotSame('failed', $job->status);
        $this->assertSame(1, Brand::query()->where('slug', 'same-code')->count());
        $this->assertSame(1, Brand::query()->where('slug', 'other-code')->count());
        $this->assertSame(1, ImportRowError::query()->where('job_id', $job->id)->count());
    }

    public function test_not_null_violation_records_row_error_and_continues(): void
    {
        Queue::fake([GenerateErrorReportJob::class]);

        $job = $this->createJob($this->csv([
            ['code' => '__null_name__', 'name' => 'ignored'],
            ['code' => 'good-brand', 'name' => 'Good'],
        ]));

        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertNotSame('failed', $job->status);
        $this->assertSame(1, Brand::query()->where('slug', 'good-brand')->count());
        $this->assertSame(1, ImportRowError::query()->where('job_id', $job->id)->count());

        $flat = json_encode(ImportRowError::query()->where('job_id', $job->id)->first()?->errors);
        $this->assertStringContainsString('Required value is missing', (string) $flat);
    }

    public function test_systemic_query_exception_fails_the_job(): void
    {
        $job = $this->createJob($this->csv([
            ['code' => '__systemic__', 'name' => 'Boom'],
            ['code' => 'should-not-run', 'name' => 'Skip'],
        ]));

        try {
            (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));
            $this->fail('Expected systemic QueryException to escape.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Unknown column', $e->getMessage());
        }

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame(0, Brand::query()->where('slug', 'should-not-run')->count());
    }

    public function test_transient_deadlock_is_retried_then_succeeds(): void
    {
        Queue::fake([GenerateErrorReportJob::class]);
        BlindBrandInsertImportHandler::failTransientTimes(2);

        $job = $this->createJob($this->csv([
            ['code' => 'retry-brand', 'name' => 'Retry Me'],
        ]));

        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(1, Brand::query()->where('slug', 'retry-brand')->count());
        $this->assertSame(0, ImportRowError::query()->where('job_id', $job->id)->count());
    }

    public function test_resume_after_mid_job_crash_does_not_duplicate_already_succeeded_rows(): void
    {
        Queue::fake([GenerateErrorReportJob::class]);
        BlindBrandInsertImportHandler::crashOnce();

        $job = $this->createJob($this->csv([
            ['code' => 'ok-1', 'name' => 'OK One'],
            ['code' => 'ok-2', 'name' => 'OK Two'],
            ['code' => '__crash_once__', 'name' => 'Crashes The First Time'],
            ['code' => 'ok-3', 'name' => 'OK Three'],
        ]));

        try {
            (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));
            $this->fail('Expected the simulated mid-job crash to escape.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated worker crash mid-job.', $e->getMessage());
        }

        // Rows before the crash already committed — both their business row
        // and their ImportRowSuccess marker, in the same transaction.
        $this->assertSame(2, Brand::query()->whereIn('slug', ['ok-1', 'ok-2'])->count());
        $this->assertSame(2, ImportRowSuccess::query()->where('job_id', $job->id)->count());

        // Simulate the queue's automatic retry (tries = 3): re-run the same job.
        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(0, ImportRowError::query()->where('job_id', $job->id)->count());
        $this->assertSame(4, ImportRowSuccess::query()->where('job_id', $job->id)->count());
        // The row count matches the input exactly — the two rows that already
        // succeeded before the crash were not reprocessed into duplicates.
        $this->assertSame(
            4,
            Brand::query()->whereIn('slug', ['ok-1', 'ok-2', '__crash_once__', 'ok-3'])->count(),
        );
        $this->assertSame(1, Brand::query()->where('slug', 'ok-1')->count());
        $this->assertSame(1, Brand::query()->where('slug', 'ok-2')->count());
    }

    /**
     * @param  list<array{code: string, name: string}>  $rows
     */
    private function csv(array $rows): string
    {
        $lines = ['code,name'];

        foreach ($rows as $row) {
            $lines[] = $row['code'].','.$row['name'];
        }

        return implode("\n", $lines)."\n";
    }

    private function createJob(string $csvContent): ImportExportJob
    {
        $path = app(ImportExportStorageManager::class)->storeContent(
            $csvContent,
            'imports/test-blind-brands/'.Str::ulid().'.csv',
        );

        return ImportExportJob::query()->create([
            'tenant_id' => 0,
            'user_id' => $this->user->id,
            'ulid' => (string) Str::ulid(),
            'type' => 'import',
            'entity_type' => 'test-blind-brands',
            'mode' => 'create',
            'is_dry_run' => false,
            'input_file_path' => $path,
            'original_filename' => 'brands.csv',
            'disk' => 'local',
            'status' => 'validated',
            'total_rows' => substr_count($csvContent, "\n") - 1,
            'column_rules_snapshot' => [
                ['column_key' => 'code', 'mapped_to' => 'code', 'display_label' => 'Brand Code', 'rules' => []],
                ['column_key' => 'name', 'mapped_to' => 'name', 'display_label' => 'Name', 'rules' => []],
            ],
            'column_mapping' => [
                'code' => 'code',
                'name' => 'name',
            ],
            'queued_at' => now(),
        ]);
    }
}
