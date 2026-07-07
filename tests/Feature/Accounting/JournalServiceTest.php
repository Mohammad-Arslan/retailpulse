<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChartOfAccount $cash;

    private ChartOfAccount $revenue;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cash = ChartOfAccount::query()->create(['code' => '1000', 'name' => 'Cash', 'type' => 'asset']);
        $this->revenue = ChartOfAccount::query()->create(['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue']);
        $this->user = User::factory()->create(['is_active' => true]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function balancedLines(float $amount = 100.0): array
    {
        return [
            ['account_id' => $this->cash->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'debit' => 0, 'credit' => $amount],
        ];
    }

    public function test_create_draft_creates_a_draft_journal_with_lines(): void
    {
        $service = app(JournalService::class);

        $entry = $service->createDraft(
            ['journal_date' => '2026-06-15', 'description' => 'Test entry'],
            $this->balancedLines(),
            $this->user->id,
        );

        $this->assertSame(JournalEntryStatus::Draft, $entry->status);
        $this->assertNotEmpty($entry->journal_number);
        $this->assertCount(2, $entry->transactions);
    }

    public function test_post_transitions_draft_journal_to_posted(): void
    {
        $service = app(JournalService::class);

        $entry = $service->createDraft(
            ['journal_date' => '2026-06-15', 'description' => 'Test entry'],
            $this->balancedLines(),
            $this->user->id,
        );

        $posted = $service->post($entry, $this->user->id);

        $this->assertSame(JournalEntryStatus::Posted, $posted->status);
        $this->assertNotNull($posted->posted_at);
        $this->assertSame($this->user->id, $posted->posted_by);
    }

    public function test_reverse_creates_a_balancing_reversal_and_marks_original_as_reversed(): void
    {
        $service = app(JournalService::class);

        $entry = $service->createDraft(
            ['journal_date' => '2026-06-15', 'description' => 'Test entry'],
            $this->balancedLines(100),
            $this->user->id,
        );
        $entry = $service->post($entry, $this->user->id);

        $reversal = $service->reverse($entry, $this->user->id);

        $this->assertSame(JournalEntryStatus::Posted, $reversal->status);
        $this->assertSame($entry->id, $reversal->reversal_of_journal_entry_id);
        $this->assertSame(JournalEntryStatus::Reversed, $entry->fresh()->status);

        $reversalCashLine = $reversal->transactions->firstWhere('account_id', $this->cash->id);
        $reversalRevenueLine = $reversal->transactions->firstWhere('account_id', $this->revenue->id);

        $this->assertSame(0.0, (float) $reversalCashLine->debit);
        $this->assertSame(100.0, (float) $reversalCashLine->credit);
        $this->assertSame(100.0, (float) $reversalRevenueLine->debit);
        $this->assertSame(0.0, (float) $reversalRevenueLine->credit);

        $this->assertSame(
            (float) $reversal->transactions->sum('debit'),
            (float) $reversal->transactions->sum('credit'),
        );
    }
}
