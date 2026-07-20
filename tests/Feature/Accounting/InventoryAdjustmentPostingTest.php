<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\ProductType;
use App\Enums\StockMovementReason;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\CostService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class InventoryAdjustmentPostingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    private Branch $branch;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();

        $this->branch = Branch::query()->create([
            'name' => 'Adjustment Branch',
            'code' => 'ADJ',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Adjustment Widget',
            'slug' => 'adjustment-widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'ADJ-WDG',
            'sell_price' => 50,
            'is_default' => true,
        ]);

        $this->user = User::factory()->create(['is_active' => true]);

        app(CostService::class)->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 100,
            unitCost: 10,
            sourceReferenceType: 'Tests\\AdjustmentGrn',
            sourceReferenceId: 1,
        );
    }

    public function test_positive_variance_posts_as_inventory_gain(): void
    {
        $this->applyCycleCountVariance(qtyDelta: 5);

        $journal = JournalEntry::query()->where('source_event', 'inventory.adjustment_gain')->with('transactions')->firstOrFail();

        $inventoryAsset = ChartOfAccount::query()->where('code', '1400')->firstOrFail();
        $gainAccount = ChartOfAccount::query()->where('code', '4200')->firstOrFail();

        $this->assertSame(50.0, (float) $journal->transactions->where('account_id', $inventoryAsset->id)->sum('debit'));
        $this->assertSame(50.0, (float) $journal->transactions->where('account_id', $gainAccount->id)->sum('credit'));
        $this->assertSame(0, JournalEntry::query()->where('source_event', 'inventory.adjustment_loss')->count());
    }

    public function test_negative_variance_posts_as_inventory_loss(): void
    {
        $this->applyCycleCountVariance(qtyDelta: -5);

        $journal = JournalEntry::query()->where('source_event', 'inventory.adjustment_loss')->with('transactions')->firstOrFail();

        $inventoryAsset = ChartOfAccount::query()->where('code', '1400')->firstOrFail();
        $shrinkageAccount = ChartOfAccount::query()->where('code', '5700')->firstOrFail();

        $this->assertSame(50.0, (float) $journal->transactions->where('account_id', $shrinkageAccount->id)->sum('debit'));
        $this->assertSame(50.0, (float) $journal->transactions->where('account_id', $inventoryAsset->id)->sum('credit'));
        $this->assertSame(0, JournalEntry::query()->where('source_event', 'inventory.adjustment_gain')->count());
    }

    private function applyCycleCountVariance(int $qtyDelta): void
    {
        app(InventoryService::class)->applyDelta(
            warehouseId: $this->warehouse->id,
            variantId: $this->variant->id,
            batchId: null,
            qtyDelta: $qtyDelta,
            reason: StockMovementReason::CycleCountAdjustment,
            userId: $this->user->id,
        );
    }
}
