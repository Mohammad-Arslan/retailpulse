<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Enums\PickingStrategy;
use App\Enums\ProductType;
use App\Jobs\GenerateErrorReportJob;
use App\Jobs\ProcessImportJob;
use App\Models\Branch;
use App\Models\ImportExportJob;
use App\Models\ImportRowError;
use App\Models\ImportRowSuccess;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Services\ImportExport\Validation\DynamicRuleEngine;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

/**
 * For an insert-only handler like InventoryImportHandler, a queue retry (tries = 3)
 * re-running a job after a mid-run crash must not re-attempt rows that already
 * committed — doing so would call setOpeningBalance() again and turn a prior
 * success into an "already exists" failure. The resume checkpoint (via
 * ImportRowSuccess, written in the same transaction as the row's own effects)
 * must be authoritative: already-committed rows are skipped, never replayed.
 */
final class InventoryImportResumeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private User $user;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->user = User::factory()->create(['is_active' => true]);

        $branch = Branch::query()->create([
            'name' => 'Test Branch',
            'code' => 'TST',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'picking_strategy' => PickingStrategy::Fifo,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'name' => 'Piece',
            'abbreviation' => 'pc',
            'is_active' => true,
        ]);

        foreach (['WDG-001', 'WDG-002', 'WDG-003'] as $sku) {
            $product = Product::query()->create([
                'type' => ProductType::Standard,
                'name' => $sku,
                'slug' => mb_strtolower($sku),
                'unit_id' => $unit->id,
                'is_active' => true,
            ]);

            ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => $sku,
                'sell_price' => 100,
                'is_default' => true,
            ]);
        }
    }

    public function test_retry_after_crash_does_not_refail_an_already_committed_row_as_already_existing(): void
    {
        Queue::fake([GenerateErrorReportJob::class]);

        $job = $this->createJob();

        // Row index 1 (the first data row) "already succeeded" before a simulated
        // worker crash: its own business effect and its ImportRowSuccess marker
        // committed together in a prior attempt.
        app(InventoryService::class)->setOpeningBalance(
            warehouseId: $this->warehouse->id,
            variantId: ProductVariant::query()->where('sku', 'WDG-001')->value('id'),
            batchId: null,
            quantity: 5,
        );
        ImportRowSuccess::query()->create(['job_id' => $job->id, 'row_index' => 1]);

        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(0, ImportRowError::query()->where('job_id', $job->id)->count());
        $this->assertSame(
            $job->total_rows,
            $job->success_rows + $job->failed_rows + $job->skipped_rows,
        );

        // Untouched by a replay — still exactly the quantity from the first attempt.
        $this->assertSame(
            5,
            Inventory::query()
                ->where('warehouse_id', $this->warehouse->id)
                ->where('product_variant_id', ProductVariant::query()->where('sku', 'WDG-001')->value('id'))
                ->value('quantity_on_hand'),
        );

        // The rows that had not yet run are processed normally.
        $this->assertSame(
            7,
            Inventory::query()
                ->where('warehouse_id', $this->warehouse->id)
                ->where('product_variant_id', ProductVariant::query()->where('sku', 'WDG-002')->value('id'))
                ->value('quantity_on_hand'),
        );
        $this->assertSame(
            3,
            Inventory::query()
                ->where('warehouse_id', $this->warehouse->id)
                ->where('product_variant_id', ProductVariant::query()->where('sku', 'WDG-003')->value('id'))
                ->value('quantity_on_hand'),
        );
    }

    public function test_replace_mode_updates_an_existing_balance_instead_of_failing(): void
    {
        Queue::fake([GenerateErrorReportJob::class]);

        $variantId = ProductVariant::query()->where('sku', 'WDG-001')->value('id');

        app(InventoryService::class)->setOpeningBalance(
            warehouseId: $this->warehouse->id,
            variantId: $variantId,
            batchId: null,
            quantity: 5,
        );

        $csv = "warehouse_code,sku,qty,unit_cost,batch_no,bin_code\nMAIN,WDG-001,20,10,,\n";
        $path = app(ImportExportStorageManager::class)->storeContent(
            $csv,
            'imports/inventory/'.Str::ulid().'.csv',
        );
        $columns = ['warehouse_code', 'sku', 'qty', 'unit_cost', 'batch_no', 'bin_code'];

        $job = ImportExportJob::query()->create([
            'tenant_id' => 0,
            'user_id' => $this->user->id,
            'ulid' => (string) Str::ulid(),
            'type' => 'import',
            'entity_type' => 'inventory',
            'mode' => 'update',
            'is_dry_run' => false,
            'input_file_path' => $path,
            'original_filename' => 'opening-stock.csv',
            'disk' => 'local',
            'status' => 'validated',
            'total_rows' => 1,
            'column_rules_snapshot' => array_map(
                fn (string $key): array => ['column_key' => $key, 'mapped_to' => $key, 'display_label' => $key, 'rules' => []],
                $columns,
            ),
            'column_mapping' => array_combine($columns, $columns),
            'queued_at' => now(),
        ]);

        (new ProcessImportJob($job->id))->handle(app(DynamicRuleEngine::class));

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(0, ImportRowError::query()->where('job_id', $job->id)->count());
        $this->assertSame(
            20,
            Inventory::query()
                ->where('warehouse_id', $this->warehouse->id)
                ->where('product_variant_id', $variantId)
                ->value('quantity_on_hand'),
        );
        // Replace mode updates the existing row rather than creating a second one.
        $this->assertSame(
            1,
            Inventory::query()
                ->where('warehouse_id', $this->warehouse->id)
                ->where('product_variant_id', $variantId)
                ->count(),
        );
    }

    private function createJob(): ImportExportJob
    {
        $csv = "warehouse_code,sku,qty,unit_cost,batch_no,bin_code\n"
            ."MAIN,WDG-001,5,10,,\n"
            ."MAIN,WDG-002,7,12,,\n"
            ."MAIN,WDG-003,3,8,,\n";

        $path = app(ImportExportStorageManager::class)->storeContent(
            $csv,
            'imports/inventory/'.Str::ulid().'.csv',
        );

        $columns = ['warehouse_code', 'sku', 'qty', 'unit_cost', 'batch_no', 'bin_code'];

        return ImportExportJob::query()->create([
            'tenant_id' => 0,
            'user_id' => $this->user->id,
            'ulid' => (string) Str::ulid(),
            'type' => 'import',
            'entity_type' => 'inventory',
            'mode' => 'create',
            'is_dry_run' => false,
            'input_file_path' => $path,
            'original_filename' => 'opening-stock.csv',
            'disk' => 'local',
            'status' => 'validated',
            'total_rows' => 3,
            'column_rules_snapshot' => array_map(
                fn (string $key): array => [
                    'column_key' => $key,
                    'mapped_to' => $key,
                    'display_label' => $key,
                    'rules' => [],
                ],
                $columns,
            ),
            'column_mapping' => array_combine($columns, $columns),
            'queued_at' => now(),
        ]);
    }
}
