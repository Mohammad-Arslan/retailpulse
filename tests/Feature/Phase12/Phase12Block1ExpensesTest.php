<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Enums\JournalEntryStatus;
use App\Enums\TaxCalculationMethod;
use App\Enums\TaxDirection;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseApprovalPolicy;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\OrganizationEntity;
use App\Models\TaxType;
use App\Models\User;
use App\Services\Expense\ExpenseService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsAccounting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Block1ExpensesTest extends TestCase
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
            'name' => 'Expense Test Branch',
            'code' => 'EXP1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Expense Test Entity',
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

    public function test_high_amount_expense_requires_approval_before_posting(): void
    {
        ExpenseApprovalPolicy::query()->create([
            'branch_id' => $this->branch->id,
            'min_amount' => '500.0000',
            'requires' => 'manager',
            'status' => 'active',
        ]);

        $service = app(ExpenseService::class);

        $expense = $service->create([
            'expense_category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'legal_entity_id' => $this->entity->id,
            'currency_code' => 'USD',
            'amount' => '1000.0000',
            'tax_amount' => '0',
            'expense_date' => '2026-07-15',
        ], (int) $this->admin->id);

        $this->assertTrue($expense->approval_required);
        $this->assertSame('draft', $expense->status);
        $this->assertNull($expense->journal_entry_id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Expense Requires Approval Before Posting.');

        $service->approve($expense, (int) $this->admin->id);
    }

    public function test_submit_and_approve_posts_balanced_journal_for_high_amount_expense(): void
    {
        ExpenseApprovalPolicy::query()->create([
            'branch_id' => $this->branch->id,
            'min_amount' => '500.0000',
            'requires' => 'manager',
            'status' => 'active',
        ]);

        $service = app(ExpenseService::class);

        $expense = $service->create([
            'expense_category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'legal_entity_id' => $this->entity->id,
            'currency_code' => 'USD',
            'amount' => '1000.0000',
            'tax_amount' => '0',
            'expense_date' => '2026-07-15',
        ], (int) $this->admin->id);

        $pending = $service->submitForApproval($expense, (int) $this->admin->id);

        $this->assertSame('pending_approval', $pending->status);
        $this->assertNull($pending->journal_entry_id);

        $posted = $service->approve($pending, (int) $this->admin->id);

        $this->assertSame('posted', $posted->status);
        $this->assertNotNull($posted->journal_entry_id);

        $journal = JournalEntry::query()->with('transactions')->findOrFail($posted->journal_entry_id);
        $this->assertSame(JournalEntryStatus::Posted, $journal->status);
        $this->assertSame(
            (float) $journal->transactions->sum('debit'),
            (float) $journal->transactions->sum('credit'),
        );
    }

    public function test_taxable_fx_expense_posts_balanced_journal_via_accounting_event(): void
    {
        $inputTax = ChartOfAccount::query()->where('code', '1350')->firstOrFail();

        $taxType = TaxType::query()->create([
            'name' => 'GST 15%',
            'code' => 'GST15',
            'rate' => 15.00,
            'tax_direction' => TaxDirection::Purchase,
            'calculation_method' => TaxCalculationMethod::Exclusive,
            'input_tax_account_id' => $inputTax->id,
            'recoverable_percentage' => 100,
            'effective_from' => '2020-01-01',
            'status' => 'active',
        ]);

        $service = app(ExpenseService::class);

        $expense = $service->create([
            'expense_category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'legal_entity_id' => $this->entity->id,
            'currency_code' => 'EUR',
            'exchange_rate' => '1.20000000',
            'amount' => '100.0000',
            'tax_type_id' => $taxType->id,
            'tax_amount' => '15.0000',
            'expense_date' => '2026-07-15',
            'payment_method' => 'cash',
            'description' => 'FX Taxable Expense',
        ], (int) $this->admin->id);

        $posted = $service->approve($expense, (int) $this->admin->id);

        $this->assertSame('posted', $posted->status);
        $this->assertNotNull($posted->journal_entry_id);
        $this->assertNotNull($posted->accounting_event_id);

        $journal = JournalEntry::query()->with('transactions')->findOrFail($posted->journal_entry_id);
        $this->assertSame(JournalEntryStatus::Posted, $journal->status);
        $this->assertSame(
            (float) $journal->transactions->sum('debit'),
            (float) $journal->transactions->sum('credit'),
        );

        $expenseAccount = ChartOfAccount::query()->where('code', '5300')->firstOrFail();
        $this->assertTrue(
            $journal->transactions->contains(fn ($line) => (int) $line->account_id === (int) $expenseAccount->id && (float) $line->debit > 0),
        );
    }

    public function test_expense_services_do_not_reference_accounting_internals(): void
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

    public function test_expense_default_account_mapping_is_available_after_seeding(): void
    {
        $rentExpense = ChartOfAccount::query()->where('code', '5300')->first();

        $this->assertNotNull($rentExpense);
        $this->assertSame('expense_default', $this->category->resolvedAccountMappingKey());
    }
}
