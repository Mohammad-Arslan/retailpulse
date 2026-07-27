<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Jobs\ValidateImportJob;
use App\Models\Brand;
use App\Models\ImportExportJob;
use App\Models\User;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reproduces the double-counted summary bug: re-importing a file whose rows all
 * already exist reported failed == skipped == total (impossible, since
 * success + failed + skipped must equal total). The root cause was two separate
 * places re-stamping the same validation-rejected rows into both the "failed"
 * and "skipped" buckets — ValidateImportJob's dry-run/strict early return, and
 * GenerateErrorReportJob's final counter overwrite.
 */
final class ImportCounterReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function csvOf(int $rows): string
    {
        $lines = ['code,name'];
        for ($i = 0; $i < $rows; $i++) {
            $lines[] = 'dup-code,Clash '.$i;
        }

        return implode("\n", $lines)."\n";
    }

    private function makeJob(User $user, string $csv, array $overrides = []): ImportExportJob
    {
        $path = app(ImportExportStorageManager::class)->storeContent(
            $csv,
            'imports/brands/'.Str::ulid().'.csv',
        );

        return ImportExportJob::query()->create([
            'tenant_id' => 0,
            'user_id' => $user->id,
            'ulid' => (string) Str::ulid(),
            'type' => 'import',
            'entity_type' => 'brands',
            'mode' => 'create',
            'is_dry_run' => false,
            'input_file_path' => $path,
            'original_filename' => 'brands.csv',
            'disk' => 'local',
            'status' => 'pending',
            'column_rules_snapshot' => [
                ['column_key' => 'code', 'mapped_to' => 'code', 'display_label' => 'Brand Code', 'rules' => []],
                ['column_key' => 'name', 'mapped_to' => 'name', 'display_label' => 'Name', 'rules' => []],
            ],
            'column_mapping' => ['code' => 'code', 'name' => 'name'],
            'queued_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_all_duplicate_reimport_reconciles_counters_in_lenient_mode(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Brand::query()->create([
            'tenant_id' => 0,
            'slug' => 'dup-code',
            'name' => 'Existing',
            'is_active' => true,
        ]);

        $job = $this->makeJob($user, $this->csvOf(10));

        // sync queue connection: this cascades through ProcessImportJob and
        // GenerateErrorReportJob inline, exactly as it would in production.
        (new ValidateImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(10, $job->total_rows);
        $this->assertSame(
            $job->total_rows,
            $job->success_rows + $job->failed_rows + $job->skipped_rows,
            'success + failed + skipped must equal total — a row cannot be counted twice.',
        );
        $this->assertSame(0, $job->success_rows);
        $this->assertSame(0, $job->failed_rows);
        $this->assertSame(10, $job->skipped_rows);
    }

    public function test_strict_mode_abort_reconciles_counters_without_processing(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        // Blank names with a locked "required" rule guarantee every row fails
        // validation deterministically, regardless of pre-existing DB state.
        $csv = "code,name\nbrand-a,\nbrand-b,\nbrand-c,\nbrand-d,\nbrand-e,\n";

        $job = $this->makeJob($user, $csv, [
            'options' => ['strict' => true],
            'column_rules_snapshot' => [
                ['column_key' => 'code', 'mapped_to' => 'code', 'display_label' => 'Brand Code', 'rules' => []],
                ['column_key' => 'name', 'mapped_to' => 'name', 'display_label' => 'Name', 'rules' => ['required'], 'is_required' => true],
            ],
        ]);

        (new ValidateImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(5, $job->total_rows);
        $this->assertSame(
            $job->total_rows,
            $job->success_rows + $job->failed_rows + $job->skipped_rows,
        );
        $this->assertSame(0, $job->success_rows);
        $this->assertSame(0, $job->failed_rows);
        $this->assertSame(5, $job->skipped_rows);
    }

    public function test_dry_run_never_processes_and_reconciles_counters(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        // Distinct codes — none exist yet, so validation would pass every row.
        $csv = "code,name\nnew-brand-1,First\nnew-brand-2,Second\n";

        $job = $this->makeJob($user, $csv, ['is_dry_run' => true]);

        (new ValidateImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(2, $job->total_rows);
        $this->assertSame(
            $job->total_rows,
            $job->success_rows + $job->failed_rows + $job->skipped_rows,
        );
        // A dry run never reaches processRow, so nothing is "success" or "failed" —
        // every row is "skipped" (not processed), regardless of whether it would
        // have passed validation.
        $this->assertSame(0, $job->success_rows);
        $this->assertSame(0, $job->failed_rows);
        $this->assertSame(2, $job->skipped_rows);
        $this->assertDatabaseMissing('brands', ['slug' => 'new-brand-1']);
    }
}
