<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Enums\JournalEntryStatus;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\OrganizationEntity;
use App\Models\PayComponent;
use App\Models\PayrollApprovalSetting;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\User;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsAccounting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Block7PayrollPostingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;
    use SeedsRbac;

    private Branch $branch;

    private User $admin;

    private OrganizationEntity $entity;

    private PayrollRunService $runs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedAccounting();

        $this->branch = Branch::query()->create([
            'name' => 'Payroll Post Branch',
            'code' => 'PPB',
            'currency' => 'PKR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Payroll Post Entity',
            'functional_currency_code' => 'PKR',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'payroll'],
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->runs = app(PayrollRunService::class);

        PayrollApprovalSetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'requires_approval' => true,
            'approval_limit' => null,
            'use_workflow_engine' => false,
        ]);
    }

    public function test_approve_does_not_create_journal_but_post_does_balanced_and_idempotent(): void
    {
        $this->seedSimplePayrollEmployee('40000');

        $run = $this->runs->createDraft([
            'legal_entity_id' => $this->entity->id,
            'branch_id' => $this->branch->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency_code' => 'PKR',
        ]);

        $run = $this->runs->calculate($run);
        $run = $this->runs->approve($run, (int) $this->admin->id);

        $this->assertSame('approved', $run->status);
        $this->assertNull($run->journal_entry_id);
        $this->assertSame(0, JournalEntry::query()->count());

        $run = $this->runs->post($run, (int) $this->admin->id);

        $this->assertSame('posted', $run->status);
        $this->assertNotNull($run->journal_entry_id);

        $journal = JournalEntry::query()->with('transactions')->findOrFail($run->journal_entry_id);
        $debits = '0';
        $credits = '0';
        foreach ($journal->transactions as $tx) {
            $debits = bcadd($debits, (string) $tx->debit, 4);
            $credits = bcadd($credits, (string) $tx->credit, 4);
        }
        $this->assertSame(0, bccomp($debits, $credits, 4), 'Journal Must Balance');

        $firstJournalId = $run->journal_entry_id;
        $countBefore = JournalEntry::query()->count();

        $run = $this->runs->post($run->fresh(), (int) $this->admin->id);
        $this->assertSame($firstJournalId, $run->journal_entry_id);
        $this->assertSame($countBefore, JournalEntry::query()->count());
    }

    public function test_reversal_produces_linked_reversal_journal(): void
    {
        $this->seedSimplePayrollEmployee('25000');

        $run = $this->runs->createDraft([
            'legal_entity_id' => $this->entity->id,
            'branch_id' => $this->branch->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency_code' => 'PKR',
        ]);

        $run = $this->runs->calculate($run);
        $run = $this->runs->approve($run, (int) $this->admin->id);
        $run = $this->runs->post($run, (int) $this->admin->id);

        $originalId = $run->journal_entry_id;
        $run = $this->runs->reverse($run, (int) $this->admin->id);

        $this->assertSame('reversed', $run->status);
        $original = JournalEntry::query()->findOrFail($originalId);
        $this->assertSame(JournalEntryStatus::Reversed, $original->status);

        $reversal = JournalEntry::query()
            ->where('reversal_of_journal_entry_id', $originalId)
            ->first();
        $this->assertNotNull($reversal);

        $payrollServices = glob(app_path('Services/Payroll/*.php')) ?: [];
        foreach ($payrollServices as $file) {
            $contents = file_get_contents($file) ?: '';
            $this->assertStringNotContainsString('use App\\Services\\Accounting\\JournalService', $contents);
            $this->assertStringNotContainsString('use App\\Services\\Accounting\\PostingRuleEngine', $contents);
            $this->assertStringNotContainsString('use App\\Models\\ChartOfAccount', $contents);
        }
    }

    private function seedSimplePayrollEmployee(string $basicAmount): void
    {
        $basic = PayComponent::query()->create([
            'code' => 'BASIC',
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'taxable' => true,
            'account_mapping_key' => 'payroll_expense',
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);

        $structure = SalaryStructure::query()->create([
            'code' => 'STD',
            'name' => 'Standard',
            'legal_entity_id' => $this->entity->id,
            'status' => 'active',
        ]);

        SalaryStructureComponent::query()->create([
            'salary_structure_id' => $structure->id,
            'pay_component_id' => $basic->id,
            'amount_or_rate' => $basicAmount,
            'sequence' => 10,
        ]);

        Employee::query()->create([
            'employee_code' => 'EMP-PAY-1',
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'salary_structure_id' => $structure->id,
            'hire_date' => '2024-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Pay',
            'last_name' => 'Roller',
        ]);
    }
}
