<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\ProductType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\CostService;
use App\Services\Accounting\FinancialSettingsService;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class BackdatedPostingPolicyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    private ChartOfAccount $cash;

    private ChartOfAccount $revenue;

    private User $user;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();

        $this->cash = ChartOfAccount::query()->create(['code' => '1001', 'name' => 'Cash Test', 'type' => 'asset']);
        $this->revenue = ChartOfAccount::query()->create(['code' => '4001', 'name' => 'Revenue Test', 'type' => 'revenue']);
        $this->user = User::factory()->create(['is_active' => true]);

        $branch = Branch::query()->create([
            'name' => 'Backdate Branch',
            'code' => 'BKD',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Main',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::query()->create(['name' => 'Piece', 'abbreviation' => 'pc', 'is_active' => true]);
        $product = Product::query()->create([
            'type' => ProductType::Standard,
            'name' => 'Backdate Widget',
            'slug' => 'backdate-widget',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'BKD-WDG',
            'sell_price' => 50,
            'is_default' => true,
        ]);
    }

    private function setPolicy(string $policy): void
    {
        app(FinancialSettingsService::class)->get()->update(['backdated_posting_policy' => $policy]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function balancedLines(): array
    {
        return [
            ['account_id' => $this->cash->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 100],
        ];
    }

    public function test_backdated_draft_journal_is_blocked_under_block_policy(): void
    {
        $this->setPolicy('block');

        $this->expectException(\DomainException::class);

        app(JournalService::class)->createDraft(
            ['journal_date' => now()->subDays(5)->toDateString(), 'description' => 'Backdated'],
            $this->balancedLines(),
            $this->user->id,
        );
    }

    public function test_backdated_draft_journal_is_flagged_under_warn_policy(): void
    {
        $this->setPolicy('warn');

        $entry = app(JournalService::class)->createDraft(
            ['journal_date' => now()->subDays(5)->toDateString(), 'description' => 'Backdated'],
            $this->balancedLines(),
            $this->user->id,
        );

        $this->assertNotNull($entry->backdated_at);
        $this->assertNotNull($entry->backdated_reason);
    }

    public function test_backdated_draft_journal_is_unflagged_under_allow_policy(): void
    {
        $this->setPolicy('allow');

        $entry = app(JournalService::class)->createDraft(
            ['journal_date' => now()->subDays(5)->toDateString(), 'description' => 'Backdated'],
            $this->balancedLines(),
            $this->user->id,
        );

        $this->assertNull($entry->backdated_at);
        $this->assertNull($entry->backdated_reason);
    }

    public function test_non_backdated_draft_journal_is_never_flagged(): void
    {
        $this->setPolicy('warn');

        $entry = app(JournalService::class)->createDraft(
            ['journal_date' => now()->toDateString(), 'description' => 'Current'],
            $this->balancedLines(),
            $this->user->id,
        );

        $this->assertNull($entry->backdated_at);
    }

    public function test_updating_draft_to_a_backdated_date_is_blocked_under_block_policy(): void
    {
        $this->setPolicy('allow');

        $service = app(JournalService::class);
        $entry = $service->createDraft(
            ['journal_date' => now()->toDateString(), 'description' => 'Current'],
            $this->balancedLines(),
            $this->user->id,
        );

        $this->setPolicy('block');

        $this->expectException(\DomainException::class);

        $service->updateDraft(
            $entry,
            ['journal_date' => now()->subDays(5)->toDateString()],
            $this->balancedLines(),
            $this->user->id,
        );
    }

    public function test_system_generated_journal_is_never_backdated_checked(): void
    {
        $this->setPolicy('block');

        $entry = app(JournalService::class)->createDraft(
            [
                'journal_date' => now()->subDays(5)->toDateString(),
                'description' => 'Reversal-like',
                'is_system_generated' => true,
            ],
            $this->balancedLines(),
            $this->user->id,
        );

        $this->assertNull($entry->backdated_at);
    }

    public function test_backdated_grn_receipt_is_blocked_under_block_policy(): void
    {
        $costService = app(CostService::class);
        $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 20,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 1,
        );

        $this->setPolicy('block');

        $this->expectException(ValidationException::class);

        $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 5,
            unitCost: 20,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 2,
            receivedAt: now()->subDays(5),
        );
    }

    public function test_backdated_grn_receipt_is_flagged_under_warn_policy(): void
    {
        $costService = app(CostService::class);
        $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 20,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 3,
        );

        $this->setPolicy('warn');

        $layer = $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 5,
            unitCost: 20,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 4,
            receivedAt: now()->subDays(5),
        );

        $this->assertNotNull($layer->backdated_at);
        $this->assertNotNull($layer->backdated_reason);
    }

    public function test_backdated_grn_receipt_is_unflagged_under_allow_policy(): void
    {
        $costService = app(CostService::class);
        $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 20,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 5,
        );

        $this->setPolicy('allow');

        $layer = $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 5,
            unitCost: 20,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 6,
            receivedAt: now()->subDays(5),
        );

        $this->assertNull($layer->backdated_at);
        $this->assertNull($layer->backdated_reason);
    }

    public function test_grn_receipt_not_reordering_history_is_never_flagged(): void
    {
        $this->setPolicy('warn');

        $costService = app(CostService::class);
        $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 10,
            unitCost: 20,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 7,
            receivedAt: now()->subDays(10),
        );

        $layer = $costService->createLayerOnReceive(
            productVariantId: $this->variant->id,
            warehouseId: $this->warehouse->id,
            qtyReceived: 5,
            unitCost: 20,
            sourceReferenceType: 'Tests\\Grn',
            sourceReferenceId: 8,
            receivedAt: now()->subDays(5),
        );

        $this->assertNull($layer->backdated_at);
    }
}
