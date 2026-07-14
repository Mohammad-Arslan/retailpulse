<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Enums\JournalEntryStatus;
use App\Models\AccountingEvent;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\OrganizationEntity;
use App\Models\RecurringExpenseOccurrence;
use App\Models\RecurringExpenseSchedule;
use App\Models\User;
use App\Services\Expense\RecurringExpenseScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsAccounting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Block2RecurringExpensesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;
    use SeedsRbac;

    private Branch $branch;

    private User $admin;

    private OrganizationEntity $entity;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
        $this->seedAccounting();

        $this->branch = Branch::query()->create([
            'name' => 'Recurring Expense Branch',
            'code' => 'REXP',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Recurring Expense Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['expenses'],
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->category = ExpenseCategory::query()->create([
            'code' => 'RENT',
            'name' => 'Rent',
            'is_group' => false,
            'requires_receipt' => false,
            'status' => 'active',
        ]);
    }

    public function test_monthly_schedule_generates_one_occurrence_per_period(): void
    {
        $schedule = $this->createMonthlySchedule('1000.0000', '2026-07-01');
        $scheduler = app(RecurringExpenseScheduler::class);

        $asOf = CarbonImmutable::parse('2026-07-01 08:00:00');
        $processed = $scheduler->processDue($asOf);

        $this->assertCount(1, $processed);
        $this->assertSame(1, RecurringExpenseOccurrence::query()->count());

        $occurrence = RecurringExpenseOccurrence::query()->firstOrFail();
        $this->assertSame('2026-07', $occurrence->period_key);
        $this->assertSame('posted', $occurrence->status);
        $this->assertSame('1000.0000', $occurrence->amount);
        $this->assertNotNull($occurrence->accounting_event_id);

        $journal = JournalEntry::query()->with('transactions')->findOrFail(
            AccountingEvent::query()->findOrFail($occurrence->accounting_event_id)->journal_entry_id,
        );
        $this->assertSame(JournalEntryStatus::Posted, $journal->status);
        $this->assertSame(
            (float) $journal->transactions->sum('debit'),
            (float) $journal->transactions->sum('credit'),
        );

        $schedule->refresh();
        $this->assertSame('2026-08-01', $schedule->next_run_at->toDateString());
    }

    public function test_scheduler_is_idempotent_for_the_same_period(): void
    {
        $schedule = $this->createMonthlySchedule('500.0000', '2026-07-15');
        $scheduler = app(RecurringExpenseScheduler::class);
        $asOf = CarbonImmutable::parse('2026-07-15 09:00:00');

        $scheduler->processDue($asOf);

        $occurrenceCount = RecurringExpenseOccurrence::query()->count();
        $eventCount = AccountingEvent::query()
            ->where('event_type', 'expense.recurring_due')
            ->where('source_type', RecurringExpenseOccurrence::class)
            ->count();

        $this->assertSame(1, $occurrenceCount);
        $this->assertSame(1, $eventCount);

        $schedule->update(['next_run_at' => $asOf]);

        $scheduler->processDue($asOf);

        $this->assertSame($occurrenceCount, RecurringExpenseOccurrence::query()->count());
        $this->assertSame($eventCount, AccountingEvent::query()
            ->where('event_type', 'expense.recurring_due')
            ->where('source_type', RecurringExpenseOccurrence::class)
            ->count());
    }

    public function test_recurring_expense_services_do_not_reference_accounting_internals(): void
    {
        $forbidden = ['JournalService', 'PostingRuleEngine', 'ChartOfAccount'];
        $directory = app_path('Services/Expense');

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $contents = (string) file_get_contents($file);

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $contents,
                    "Forbidden reference [{$needle}] in {$file}",
                );
            }
        }
    }

    private function createMonthlySchedule(string $amount, string $startDate): RecurringExpenseSchedule
    {
        return RecurringExpenseSchedule::query()->create([
            'expense_category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'legal_entity_id' => $this->entity->id,
            'currency_code' => 'USD',
            'amount' => $amount,
            'frequency' => 'monthly',
            'interval_count' => 1,
            'day_of_period' => 1,
            'start_date' => $startDate,
            'proration_policy' => 'none',
            'next_run_at' => CarbonImmutable::parse($startDate)->startOfDay(),
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
    }
}
