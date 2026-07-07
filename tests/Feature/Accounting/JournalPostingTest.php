<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\JournalEntryStatus;
use App\Enums\PostingRuleEntrySide;
use App\Models\ChartOfAccount;
use App\Models\JournalTransaction;
use App\Models\PostingRuleSet;
use App\Models\User;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PostingRuleEngine;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class JournalPostingTest extends TestCase
{
    use RefreshDatabase;

    private ChartOfAccount $cash;

    private ChartOfAccount $revenue;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cash = ChartOfAccount::query()->create([
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $this->revenue = ChartOfAccount::query()->create([
            'code' => '4000',
            'name' => 'Revenue',
            'type' => 'revenue',
        ]);

        $this->user = User::factory()->create(['is_active' => true]);
    }

    public function test_posting_rule_built_journal_posts_successfully_when_balanced(): void
    {
        $ruleSet = PostingRuleSet::query()->create([
            'code' => 'TEST-SALE-COMPLETED',
            'name' => 'Sale Completed',
            'event_type' => 'sale.completed',
            'effective_from' => '2020-01-01',
        ]);

        $ruleSet->lines()->create([
            'sequence' => 1,
            'entry_side' => PostingRuleEntrySide::Debit,
            'account_resolution_type' => AccountResolutionType::FixedAccount,
            'account_id' => $this->cash->id,
            'amount_source' => AmountSource::GrossAmount,
            'required' => true,
        ]);

        $ruleSet->lines()->create([
            'sequence' => 2,
            'entry_side' => PostingRuleEntrySide::Credit,
            'account_resolution_type' => AccountResolutionType::FixedAccount,
            'account_id' => $this->revenue->id,
            'amount_source' => AmountSource::GrossAmount,
            'required' => true,
        ]);

        $lines = app(PostingRuleEngine::class)->buildJournalLines('sale.completed', [
            'date' => '2026-06-15',
            'gross_amount' => 100.0,
            'net_amount' => 100.0,
        ]);

        $journalService = app(JournalService::class);

        $entry = $journalService->createDraft([
            'journal_date' => '2026-06-15',
            'description' => 'Sale posting test',
            'source_event' => 'sale.completed',
            'is_system_generated' => true,
        ], $lines, $this->user->id);

        $posted = $journalService->post($entry, $this->user->id);

        $this->assertSame(JournalEntryStatus::Posted, $posted->status);
        $this->assertSame(
            (float) $posted->transactions->sum('debit'),
            (float) $posted->transactions->sum('credit'),
        );
    }

    public function test_post_rejects_manually_unbalanced_draft(): void
    {
        $journalService = app(JournalService::class);

        $entry = $journalService->createDraft([
            'journal_date' => '2026-06-15',
            'description' => 'Unbalanced draft',
        ], [
            ['account_id' => $this->cash->id, 'debit' => 100.0, 'credit' => 0.0],
            ['account_id' => $this->revenue->id, 'debit' => 0.0, 'credit' => 99.99],
        ], $this->user->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is not balanced');

        $journalService->post($entry, $this->user->id);
    }

    public function test_post_rejects_draft_after_manual_line_edit_creates_imbalance(): void
    {
        $journalService = app(JournalService::class);

        $entry = $journalService->createDraft([
            'journal_date' => '2026-06-15',
            'description' => 'Balanced then edited',
        ], [
            ['account_id' => $this->cash->id, 'debit' => 100.0, 'credit' => 0.0],
            ['account_id' => $this->revenue->id, 'debit' => 0.0, 'credit' => 100.0],
        ], $this->user->id);

        $creditLine = $entry->transactions->firstWhere('account_id', $this->revenue->id);
        $this->assertNotNull($creditLine);

        JournalTransaction::query()
            ->whereKey($creditLine->id)
            ->update(['credit' => 50.0]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('difference: 50.00');

        $journalService->post($entry->fresh(['transactions']), $this->user->id);
    }
}
