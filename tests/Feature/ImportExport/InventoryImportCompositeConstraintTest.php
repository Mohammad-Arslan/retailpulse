<?php

declare(strict_types=1);

namespace Tests\Feature\ImportExport;

use App\Enums\PickingStrategy;
use App\Enums\ProductType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\ImportExport\Handlers\InventoryImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

/**
 * InventoryImportHandler::validateRow() used to be a no-op — the
 * warehouse+variant+batch(+bin) uniqueness that setOpeningBalance() enforces was
 * only discovered at processing time, one row at a time, and never caught a
 * duplicate that appeared twice in the same file. These exercise both pre-flight
 * paths directly against the handler, the same way ValidateImportJob calls it.
 */
final class InventoryImportCompositeConstraintTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

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

        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Widget',
            'slug' => 'widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'WDG-001',
            'sell_price' => 100,
            'is_default' => true,
        ]);
    }

    private function context(): ImportContext
    {
        return new ImportContext(
            jobId: 1,
            tenantId: null,
            userId: 1,
            mode: 'create',
            isDryRun: false,
            filePath: 'irrelevant.csv',
            disk: 'local',
            options: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'warehouse_code' => 'MAIN',
            'sku' => 'WDG-001',
            'qty' => 5,
            'unit_cost' => 10,
            'batch_no' => '',
            'bin_code' => '',
        ];
    }

    public function test_row_that_would_conflict_with_an_existing_opening_balance_is_flagged_at_validation(): void
    {
        app(InventoryService::class)->setOpeningBalance(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 10,
        );

        $handler = app(InventoryImportHandler::class);
        $errors = $handler->validateRow($this->row(), $this->context());

        $this->assertArrayHasKey('sku', $errors);
        $this->assertStringContainsString('already exists', $errors['sku'][0]);
    }

    public function test_row_with_no_conflict_passes_validation(): void
    {
        $handler = app(InventoryImportHandler::class);
        $errors = $handler->validateRow($this->row(), $this->context());

        $this->assertSame([], $errors);
    }

    public function test_replace_mode_does_not_flag_an_existing_balance_as_an_error(): void
    {
        app(InventoryService::class)->setOpeningBalance(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            quantity: 10,
        );

        $handler = app(InventoryImportHandler::class);
        $context = new ImportContext(
            jobId: 1,
            tenantId: null,
            userId: 1,
            mode: 'update',
            isDryRun: false,
            filePath: 'irrelevant.csv',
            disk: 'local',
            options: [],
        );

        $errors = $handler->validateRow($this->row(), $context);

        $this->assertSame([], $errors);
    }

    public function test_second_occurrence_of_the_same_combination_in_one_file_is_flagged_at_validation(): void
    {
        $handler = app(InventoryImportHandler::class);
        $context = $this->context();

        $first = $handler->validateRow($this->row(), $context);
        $second = $handler->validateRow($this->row(), $context);

        $this->assertSame([], $first, 'The first occurrence in the file must pass.');
        $this->assertArrayHasKey('sku', $second);
        $this->assertStringContainsString('duplicated elsewhere in this file', $second['sku'][0]);
    }
}
