<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\AccountingEventStatus;
use App\Enums\FiscalYearStatus;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingEvent;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountingEventService;
use Database\Seeders\AccountMappingsSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PostingRulesSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountingEventPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountMappingsSeeder::class);
        $this->seed(PostingRulesSeeder::class);

        FiscalYear::query()->create([
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Open,
        ]);
    }

    public function test_sale_completed_event_posts_balanced_journal(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $service = app(AccountingEventService::class);

        $event = $service->process(
            'sale.completed',
            'App\\Models\\Sale',
            101,
            [
                'date' => '2026-06-15',
                'gross_amount' => 115.0,
                'net_amount' => 100.0,
                'tax_amount' => 15.0,
                'settlement_amount' => 115.0,
                'payment_method' => 'cash',
                'inventory_cost' => 40.0,
                'description' => 'Sale #101',
                'user_id' => $user->id,
            ],
            $user->id,
        );

        $this->assertSame(AccountingEventStatus::Completed, $event->processing_status);
        $this->assertNotNull($event->journal_entry_id);

        $journal = JournalEntry::query()->with('transactions')->findOrFail($event->journal_entry_id);
        $this->assertSame(JournalEntryStatus::Posted, $journal->status);
        $this->assertSame(
            (float) $journal->transactions->sum('debit'),
            (float) $journal->transactions->sum('credit'),
        );
    }

    public function test_duplicate_event_is_idempotent(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $service = app(AccountingEventService::class);

        $payload = [
            'date' => '2026-06-15',
            'gross_amount' => 50.0,
            'net_amount' => 50.0,
            'settlement_amount' => 50.0,
            'payment_method' => 'cash',
            'user_id' => $user->id,
        ];

        $service->process('sale.completed', 'App\\Models\\Sale', 202, $payload, $user->id);
        $service->process('sale.completed', 'App\\Models\\Sale', 202, $payload, $user->id);

        $this->assertSame(1, AccountingEvent::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());
    }

    public function test_unknown_event_type_is_marked_skipped(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $event = app(AccountingEventService::class)->process(
            'unknown.event',
            'App\\Models\\Sale',
            303,
            ['date' => '2026-06-15'],
            $user->id,
        );

        $this->assertSame(AccountingEventStatus::Skipped, $event->processing_status);
        $this->assertNull($event->journal_entry_id);
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_posting_to_closed_fiscal_year_marks_event_failed(): void
    {
        FiscalYear::query()->update(['status' => FiscalYearStatus::Closed, 'closed_at' => now()]);

        $user = User::factory()->create(['is_active' => true]);

        try {
            app(AccountingEventService::class)->process(
                'sale.completed',
                'App\\Models\\Sale',
                404,
                [
                    'date' => '2026-06-15',
                    'gross_amount' => 50.0,
                    'net_amount' => 50.0,
                    'settlement_amount' => 50.0,
                    'payment_method' => 'cash',
                    'user_id' => $user->id,
                ],
                $user->id,
            );
        } catch (DomainException) {
            // expected
        }

        $event = AccountingEvent::query()->where('source_id', 404)->first();
        $this->assertNotNull($event);
        $this->assertSame(AccountingEventStatus::Failed, $event->processing_status);
    }

    public function test_stale_processing_event_is_recovered_on_retry(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $event = AccountingEvent::query()->create([
            'event_type' => 'sale.completed',
            'source_type' => 'App\\Models\\Sale',
            'source_id' => 505,
            'idempotency_key' => 'sale.completed:App\\Models\\Sale:505',
            'processing_status' => AccountingEventStatus::Processing,
            'payload' => [
                'date' => '2026-06-15',
                'gross_amount' => 25.0,
                'net_amount' => 25.0,
                'settlement_amount' => 25.0,
                'payment_method' => 'cash',
                'user_id' => $user->id,
            ],
            'updated_at' => now()->subMinutes(10),
        ]);

        $retried = app(AccountingEventService::class)->retry($event->fresh(), $user->id);

        $this->assertSame(AccountingEventStatus::Completed, $retried->processing_status);
        $this->assertNotNull($retried->journal_entry_id);
    }
}
