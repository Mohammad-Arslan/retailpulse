<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\CostCentreAllocationMethod;
use App\Enums\JournalEntryStatus;
use App\Models\ChartOfAccount;
use App\Models\CostCentre;
use App\Models\CostCentreAllocation;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\CostCentreAllocationService;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class CostCentreAllocationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    public function test_equal_split_allocation_splits_amount_and_posts_balanced_reclass(): void
    {
        $this->seedAccounting();

        $user = User::factory()->create(['is_active' => true]);
        $expense = ChartOfAccount::query()->create([
            'code' => '5999',
            'name' => 'Shared Rent',
            'type' => 'expense',
        ]);

        $ccA = CostCentre::query()->create(['code' => 'CCA', 'name' => 'Centre A', 'status' => 'active']);
        $ccB = CostCentre::query()->create(['code' => 'CCB', 'name' => 'Centre B', 'status' => 'active']);

        $cash = ChartOfAccount::query()->where('code', '1100')->firstOrFail();

        $journalService = app(JournalService::class);
        $entry = $journalService->createDraft(
            ['journal_date' => '2026-06-01', 'description' => 'Shared rent'],
            [
                ['account_id' => $expense->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100],
            ],
            $user->id,
        );
        $posted = $journalService->post($entry, $user->id);
        $source = $posted->transactions()->where('account_id', $expense->id)->firstOrFail();

        $result = app(CostCentreAllocationService::class)->allocate(
            $source,
            CostCentreAllocationMethod::EqualSplit,
            [
                ['cost_centre_id' => $ccA->id],
                ['cost_centre_id' => $ccB->id],
            ],
            $user->id,
        );

        $this->assertCount(2, $result['allocations']);
        $this->assertSame(0, bccomp((string) $result['allocations'][0]->allocated_amount, '50.00', 2));
        $this->assertSame(0, bccomp((string) $result['allocations'][1]->allocated_amount, '50.00', 2));
        $this->assertSame(2, CostCentreAllocation::query()->count());

        $reclass = $result['journal'];
        $this->assertSame(JournalEntryStatus::Posted, $reclass->status);
        $this->assertSame(
            0,
            bccomp(
                (string) $reclass->transactions()->sum('debit'),
                (string) $reclass->transactions()->sum('credit'),
                2,
            ),
        );

        $this->assertSame(50.0, (float) $reclass->transactions()
            ->where('cost_centre_id', $ccA->id)
            ->sum('debit'));
        $this->assertSame(50.0, (float) $reclass->transactions()
            ->where('cost_centre_id', $ccB->id)
            ->sum('debit'));
    }

    public function test_percentage_allocation_respects_explicit_percents(): void
    {
        $this->seedAccounting();
        $user = User::factory()->create(['is_active' => true]);
        $expense = ChartOfAccount::query()->create(['code' => '5998', 'name' => 'Utilities', 'type' => 'expense']);
        $cash = ChartOfAccount::query()->where('code', '1100')->firstOrFail();

        $ccA = CostCentre::query()->create(['code' => 'UT1', 'name' => 'Store 1', 'status' => 'active']);
        $ccB = CostCentre::query()->create(['code' => 'UT2', 'name' => 'Store 2', 'status' => 'active']);

        $journalService = app(JournalService::class);
        $posted = $journalService->post(
            $journalService->createDraft(
                ['journal_date' => '2026-06-02', 'description' => 'Utilities'],
                [
                    ['account_id' => $expense->id, 'debit' => 200, 'credit' => 0],
                    ['account_id' => $cash->id, 'debit' => 0, 'credit' => 200],
                ],
                $user->id,
            ),
            $user->id,
        );
        $source = $posted->transactions()->where('account_id', $expense->id)->firstOrFail();

        $result = app(CostCentreAllocationService::class)->allocate(
            $source,
            CostCentreAllocationMethod::Percentage,
            [
                ['cost_centre_id' => $ccA->id, 'percent' => 70],
                ['cost_centre_id' => $ccB->id, 'percent' => 30],
            ],
            $user->id,
        );

        $this->assertSame(0, bccomp((string) $result['allocations'][0]->allocated_amount, '140.00', 2));
        $this->assertSame(0, bccomp((string) $result['allocations'][1]->allocated_amount, '60.00', 2));
        $this->assertSame(1, JournalEntry::query()->where('source_event', 'cost_centre.allocated')->count());
    }
}
