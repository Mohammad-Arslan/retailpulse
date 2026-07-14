<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\AccountingEventStatus;
use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use App\Models\AccountingEvent;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\PostingRuleSet;
use App\Models\User;
use App\Services\Accounting\AccountingEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountingEventServiceTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT_TYPE = 'test.sale_completed';

    protected function setUp(): void
    {
        parent::setUp();

        $cash = ChartOfAccount::query()->create([
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);

        $revenue = ChartOfAccount::query()->create([
            'code' => '4000',
            'name' => 'Revenue',
            'type' => 'revenue',
        ]);

        $ruleSet = PostingRuleSet::query()->create([
            'code' => 'TEST-SALE',
            'name' => 'Test Sale',
            'event_type' => self::EVENT_TYPE,
            'effective_from' => '2020-01-01',
        ]);

        $ruleSet->lines()->create([
            'sequence' => 1,
            'entry_side' => PostingRuleEntrySide::Debit,
            'account_resolution_type' => AccountResolutionType::FixedAccount,
            'account_id' => $cash->id,
            'amount_source' => AmountSource::GrossAmount,
            'required' => true,
        ]);

        $ruleSet->lines()->create([
            'sequence' => 2,
            'entry_side' => PostingRuleEntrySide::Credit,
            'account_resolution_type' => AccountResolutionType::FixedAccount,
            'account_id' => $revenue->id,
            'amount_source' => AmountSource::GrossAmount,
            'required' => true,
        ]);
    }

    public function test_process_is_idempotent_for_the_same_event(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $service = app(AccountingEventService::class);

        $payload = ['gross_amount' => 100, 'date' => '2026-06-15', 'description' => 'Sale #1'];

        $service->process(self::EVENT_TYPE, 'App\\Models\\Sale', 1, $payload, $user->id);
        $service->process(self::EVENT_TYPE, 'App\\Models\\Sale', 1, $payload, $user->id);

        $this->assertSame(1, AccountingEvent::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());
    }

    public function test_create_or_fetch_existing_recovers_when_another_process_already_inserted_the_event(): void
    {
        $idempotencyKey = self::EVENT_TYPE.':App\\Models\\Sale:2';

        $racingEvent = AccountingEvent::query()->create([
            'event_type' => self::EVENT_TYPE,
            'source_type' => 'App\\Models\\Sale',
            'source_id' => 2,
            'idempotency_key' => $idempotencyKey,
            'processing_status' => AccountingEventStatus::Pending,
            'payload' => ['gross_amount' => 50],
        ]);

        $service = app(AccountingEventService::class);
        $method = new \ReflectionMethod($service, 'createOrFetchExisting');

        $result = $method->invoke(
            $service,
            self::EVENT_TYPE,
            'App\\Models\\Sale',
            2,
            $idempotencyKey,
            ['gross_amount' => 50],
        );

        $this->assertSame($racingEvent->id, $result->id);
        $this->assertSame(1, AccountingEvent::query()->where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_process_marks_unknown_event_as_skipped(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $event = app(AccountingEventService::class)->process(
            'missing.event.type',
            'App\\Models\\Sale',
            99,
            ['date' => '2026-06-15'],
            $user->id,
        );

        $this->assertSame(AccountingEventStatus::Skipped, $event->processing_status);
    }

    public function test_retry_of_in_flight_processing_event_does_not_create_a_second_journal(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $event = AccountingEvent::query()->create([
            'event_type' => self::EVENT_TYPE,
            'source_type' => 'App\\Models\\Sale',
            'source_id' => 777,
            'idempotency_key' => self::EVENT_TYPE.':App\\Models\\Sale:777',
            'processing_status' => AccountingEventStatus::Processing,
            'payload' => [
                'gross_amount' => 40,
                'date' => '2026-06-15',
                'user_id' => $user->id,
            ],
            'updated_at' => now(),
        ]);

        $retried = app(AccountingEventService::class)->retry($event->fresh(), $user->id);

        $this->assertSame(AccountingEventStatus::Processing, $retried->processing_status);
        $this->assertNull($retried->journal_entry_id);
        $this->assertSame(0, JournalEntry::query()->count());
    }
}
