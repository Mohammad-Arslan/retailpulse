<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Events\ImportExport\ImportCompleted;
use App\Events\ImportExport\ImportProgressUpdated;
use App\Jobs\ProcessImportJob;
use App\Models\ImportExportJob;
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
 * Regression coverage for the "tray/popup restart progress after the import
 * already completed" bug: ProcessImportJob used to dispatch one more
 * `.progress.updated` (phase: 'processing') after the loop, right before
 * `ImportCompleted` — with no ordering guarantee against the client, a
 * trailing progress event could arrive after completion and flip a
 * finished job's status back to "processing".
 */
final class ImportEventOrderingTest extends TestCase
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

    public function test_import_completed_is_the_last_event_dispatched_for_a_successful_job(): void
    {
        $order = [];
        Event::listen(ImportProgressUpdated::class, function () use (&$order): void {
            $order[] = 'progress.updated';
        });
        Event::listen(ImportCompleted::class, function () use (&$order): void {
            $order[] = 'import.completed';
        });

        $user = User::factory()->create(['is_active' => true]);
        $job = $this->createJob($user, $this->csv([
            ['code' => 'ok-brand-1', 'name' => 'OK Brand One'],
            ['code' => 'ok-brand-2', 'name' => 'OK Brand Two'],
        ]));

        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertNotEmpty($order);
        $this->assertSame('import.completed', end($order), 'ImportCompleted must be the last broadcast dispatched for the job.');
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

    private function createJob(User $user, string $csvContent): ImportExportJob
    {
        $path = app(ImportExportStorageManager::class)->storeContent(
            $csvContent,
            'imports/test-blind-brands/'.Str::ulid().'.csv',
        );

        return ImportExportJob::query()->create([
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
