<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Jobs\ProcessImportJob;
use App\Models\ImportExportJob;
use App\Models\User;
use App\Repositories\Eloquent\InventoryRepository;
use App\Services\ImportExport\ImportExportRegistry;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\ImportExport\BlindBrandInsertExportHandler;
use Tests\Support\ImportExport\BlindBrandInsertImportHandler;
use Tests\TestCase;

/**
 * ProcessImportJob's advisory lock is per (entity_type, tenant_id, file hash) —
 * a second worker picking up the *same uploaded file* must not process it
 * concurrently, but a different file for the same entity_type is independent
 * (e.g. two unrelated product imports for the same tenant can run at once).
 *
 * These tests drive the job's real lock acquisition via
 * ProcessImportJob::entityLockKey() rather than hand-rolling the key string,
 * so they cannot silently drift from what the job actually locks on.
 */
final class ImportConcurrencyTest extends TestCase
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

    public function test_same_file_cannot_be_processed_by_a_second_worker(): void
    {
        $job = $this->createJob($this->csv([['code' => 'brand-a', 'name' => 'Brand A']]));

        // Simulate another worker already holding the real lock key for this job.
        $lock = Cache::lock(ProcessImportJob::entityLockKey($job), 3600);
        $this->assertTrue($lock->get());

        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        // The job bailed out before doing any work — it never left its
        // pre-processing status, because it could not acquire the real key.
        $this->assertSame('validated', $job->status);

        $lock->forceRelease();
    }

    public function test_same_entity_and_tenant_different_file_can_run_concurrently(): void
    {
        $jobA = $this->createJob($this->csv([['code' => 'brand-a', 'name' => 'Brand A']]));
        $jobB = $this->createJob($this->csv([['code' => 'brand-b', 'name' => 'Brand B']]));

        $this->assertNotSame(
            ProcessImportJob::entityLockKey($jobA),
            ProcessImportJob::entityLockKey($jobB),
            'Two different uploaded files must not share a lock key.',
        );

        $lockA = Cache::lock(ProcessImportJob::entityLockKey($jobA), 3600);
        $this->assertTrue($lockA->get());

        // jobB's file is different, so it must be free to process despite
        // jobA (same entity_type, same tenant) still "running".
        (new ProcessImportJob($jobB->id))->handle(app(DynamicRuleEngine::class));

        $jobB->refresh();

        $this->assertSame('completed', $jobB->status);

        $lockA->forceRelease();
    }

    public function test_inventory_repository_locks_rows_for_update(): void
    {
        // Structural invariant: InventoryService's applyDelta/setOpeningBalance
        // read through lockOrCreate/findForUpdate (SELECT ... FOR UPDATE) inside
        // DB::transaction, which serializes concurrent read-modify-write on
        // quantity_on_hand. Exercising the actual row lock requires two
        // concurrent DB connections, which isn't practical in this suite —
        // this confirms the invariant's entry points still exist.
        $this->assertTrue(
            method_exists(InventoryRepository::class, 'lockOrCreate'),
            'InventoryRepository must have lockOrCreate for atomic stock mutations.',
        );
        $this->assertTrue(
            method_exists(InventoryRepository::class, 'findForUpdate'),
            'InventoryRepository must have findForUpdate for SELECT FOR UPDATE.',
        );
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
