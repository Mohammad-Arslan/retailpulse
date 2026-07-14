<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\FixedAssetStatus;
use App\Models\AccountingEvent;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AssetDepreciationService;
use App\Services\Accounting\AssetDisposalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class FixedAssetsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    private FixedAsset $asset;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();

        $this->user = User::factory()->create(['is_active' => true]);

        $branch = Branch::query()->create([
            'name' => 'Asset Branch',
            'code' => 'AST',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $assetAccount = ChartOfAccount::query()->where('code', '1610')->firstOrFail();
        $accumulatedAccount = ChartOfAccount::query()->where('code', '1620')->firstOrFail();
        $expenseAccount = ChartOfAccount::query()->where('code', '5500')->firstOrFail();

        $category = AssetCategory::query()->create([
            'name' => 'Equipment',
            'code' => 'EQP',
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'asset_account_id' => $assetAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedAccount->id,
            'depreciation_expense_account_id' => $expenseAccount->id,
            'status' => 'active',
        ]);

        $this->asset = FixedAsset::query()->create([
            'asset_code' => 'FA-001',
            'name' => 'POS Terminal',
            'category_id' => $category->id,
            'acquisition_cost' => 12000,
            'acquisition_date' => '2024-01-01',
            'useful_life_months' => 60,
            'salvage_value' => 0,
            'depreciation_method' => 'straight_line',
            'depreciation_start_date' => '2024-01-01',
            'accumulated_depreciation' => 0,
            'asset_account_id' => $assetAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedAccount->id,
            'depreciation_expense_account_id' => $expenseAccount->id,
            'branch_id' => $branch->id,
            'custodian_user_id' => $this->user->id,
            'status' => FixedAssetStatus::Active,
        ]);
    }

    public function test_acquiring_an_asset_posts_balanced_asset_and_payable_journal(): void
    {
        $category = AssetCategory::query()->firstOrFail();
        $branchId = $this->asset->branch_id;

        $asset = app(\App\Services\Accounting\FixedAssetService::class)->create(
            new \App\DTOs\Accounting\CreateFixedAssetData(
                assetCode: 'FA-NEW-100',
                name: 'Scanner',
                categoryId: $category->id,
                acquisitionCost: 2500,
                acquisitionDate: '2026-03-01',
                usefulLifeMonths: 36,
                salvageValue: 0,
                branchId: $branchId,
                legalEntityId: null,
                location: null,
            ),
        );

        $event = AccountingEvent::query()
            ->where('event_type', 'asset.acquired')
            ->where('source_id', $asset->id)
            ->firstOrFail();

        $this->assertNotNull($event->journal_entry_id);

        $journal = JournalEntry::query()->with('transactions')->findOrFail($event->journal_entry_id);
        $this->assertSame(
            (float) $journal->transactions->sum('debit'),
            (float) $journal->transactions->sum('credit'),
        );

        $assetAccountId = (int) $asset->asset_account_id;
        $payableAccountId = (int) \App\Models\AccountMapping::query()
            ->where('mapping_key', 'accounts_payable')
            ->value('account_id');

        $this->assertSame(2500.0, (float) $journal->transactions->where('account_id', $assetAccountId)->sum('debit'));
        $this->assertSame(2500.0, (float) $journal->transactions->where('account_id', $payableAccountId)->sum('credit'));
    }

    public function test_monthly_depreciation_is_two_hundred_for_twelve_thousand_over_five_years(): void
    {
        $this->assertSame(200.0, $this->asset->monthlyDepreciation());
    }

    public function test_post_twelve_months_depreciation_updates_accumulated_and_posts_journal(): void
    {
        $service = app(AssetDepreciationService::class);

        for ($month = 1; $month <= 12; $month++) {
            $period = sprintf('2024-%02d', $month);
            $service->depreciateAsset(
                $this->asset->fresh(),
                sprintf('%s-28', $period),
                $period,
            );
        }

        $this->asset->refresh();
        $this->assertSame(2400.0, (float) $this->asset->accumulated_depreciation);
        $this->assertSame(9600.0, $this->asset->netBookValue());
        $this->assertSame(
            12,
            AccountingEvent::query()->where('event_type', 'asset.depreciation_due')->count(),
        );
        $this->assertGreaterThanOrEqual(12, JournalEntry::query()->count());
    }

    public function test_dispose_asset_at_ten_thousand_posts_gain_journal(): void
    {
        $service = app(AssetDepreciationService::class);

        for ($month = 1; $month <= 12; $month++) {
            $period = sprintf('2024-%02d', $month);
            $service->depreciateAsset(
                $this->asset->fresh(),
                sprintf('%s-28', $period),
                $period,
            );
        }

        $disposed = app(AssetDisposalService::class)->dispose(
            $this->asset->fresh(),
            Carbon::parse('2025-01-15'),
            10000,
            $this->user->id,
        );

        $this->assertSame(FixedAssetStatus::Disposed, $disposed->status);
        $this->assertSame(
            1,
            AccountingEvent::query()->where('event_type', 'asset.disposed')->count(),
        );
        $this->assertGreaterThanOrEqual(13, JournalEntry::query()->count());
    }
}
