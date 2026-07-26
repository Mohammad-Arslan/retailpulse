<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Events\ImportExport\ImportCompleted;
use App\Jobs\ProcessImportJob;
use App\Models\Brand;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Models\User;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\ImportExport\BlindBrandInsertExportHandler;
use Tests\Support\ImportExport\BlindBrandInsertImportHandler;
use Tests\TestCase;

/**
 * An import that ends with row errors doesn't finish inside ProcessImportJob —
 * it hands off to GenerateErrorReportJob (queue: imports-reports), which builds
 * the error workbook and is the one that actually calls markCompleted() /
 * dispatches ImportCompleted. If that job silently failed to run (or failed to
 * finalize status), the job would stay stuck in "processing" forever with no
 * visible failure. This exercises that handoff for real, on the `sync` queue
 * connection tests run on, rather than faking GenerateErrorReportJob away.
 */
final class PartialSuccessFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BlindBrandInsertImportHandler::reset();
        ImportExportRegistry::register(
            'test-blind-brands',
            BlindBrandInsertImportHandler::class,
            BlindBrandInsertExportHandler::class,
        );
    }

    public function test_partial_success_import_finalizes_to_completed_with_errors(): void
    {
        Event::fake([ImportCompleted::class]);

        $user = User::factory()->create(['is_active' => true]);

        Brand::query()->create([
            'tenant_id' => null,
            'slug' => 'dup-code',
            'name' => 'Existing',
            'is_active' => true,
        ]);

        $csv = "code,name\ndup-code,Clash\nok-brand,OK Brand\n";
        $path = app(ImportExportStorageManager::class)->storeContent(
            $csv,
            'imports/test-blind-brands/'.Str::ulid().'.csv',
        );

        $job = ImportExportJob::query()->create([
            'tenant_id' => 0,
            'user_id' => $user->id,
            'ulid' => (string) Str::ulid(),
            'type' => 'import',
            'entity_type' => 'test-blind-brands',
            'mode' => 'create',
            'is_dry_run' => false,
            'input_file_path' => $path,
            'original_filename' => 'brands.csv',
            'disk' => 'local',
            'status' => 'validated',
            'total_rows' => 2,
            'column_rules_snapshot' => [
                ['column_key' => 'code', 'mapped_to' => 'code', 'display_label' => 'Brand Code', 'rules' => []],
                ['column_key' => 'name', 'mapped_to' => 'name', 'display_label' => 'Name', 'rules' => []],
            ],
            'column_mapping' => ['code' => 'code', 'name' => 'name'],
            'queued_at' => now(),
        ]);

        // No Queue::fake() here — QUEUE_CONNECTION=sync in tests, so dispatching
        // GenerateErrorReportJob from ProcessImportJob runs it inline, exactly
        // as it would run on the real imports-reports queue.
        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status, 'A partial-success import must not get stuck in "processing".');
        $this->assertNotNull($job->output_file_path);
        $this->assertSame(1, ImportRowError::query()->where('job_id', $job->id)->count());
        $this->assertIsArray($job->summary);
        $this->assertSame(1, $job->summary['failed'] ?? null);

        Event::assertDispatched(ImportCompleted::class, fn ($event) => $event->jobUlid === $job->ulid);
    }
}
