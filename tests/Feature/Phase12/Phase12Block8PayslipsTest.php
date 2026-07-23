<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Models\PayComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\User;
use App\Services\Payroll\EmployeeSelfServiceService;
use App\Services\Payroll\PayrollCalculationService;
use App\Services\Payroll\PayslipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Block8PayslipsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private OrganizationEntity $entity;

    private PayslipService $payslips;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        Storage::fake('local');
        config(['payroll.payslips_disk' => 'local']);

        $this->branch = Branch::query()->create([
            'name' => 'Payslip Branch',
            'code' => 'PSB',
            'currency' => 'PKR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Payslip Entity',
            'functional_currency_code' => 'PKR',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'payroll', 'employee_self_service'],
        ]);

        $this->payslips = app(PayslipService::class);
    }

    public function test_generate_payslip_creates_pdf_and_totals_match_item_and_lines(): void
    {
        $item = $this->seedCalculatedPayrollItem('35000');

        $totals = $this->payslips->resolveTotals($item);

        $this->assertSame(0, bccomp($totals['gross'], (string) $item->gross, 4));
        $this->assertSame(0, bccomp($totals['total_deductions'], (string) $item->total_deductions, 4));
        $this->assertSame(0, bccomp($totals['total_employer_contributions'], (string) $item->total_employer_contributions, 4));
        $this->assertSame(0, bccomp($totals['net_pay'], (string) $item->net_pay, 4));

        $lineGross = '0.0000';
        $lineDeductions = '0.0000';
        $lineEmployer = '0.0000';

        foreach ($item->lines as $line) {
            $type = $line->component_snapshot_json['type'] ?? null;
            $amount = (string) $line->amount;

            if (in_array($type, ['earning', 'reimbursement'], true)) {
                $lineGross = bcadd($lineGross, $amount, 4);
            } elseif (in_array($type, ['deduction', 'statutory'], true)) {
                $lineDeductions = bcadd($lineDeductions, $amount, 4);
            } elseif ($type === 'employer_contribution') {
                $lineEmployer = bcadd($lineEmployer, $amount, 4);
            }
        }

        $this->assertSame(0, bccomp($totals['gross'], $lineGross, 4));
        $this->assertSame(0, bccomp($totals['total_deductions'], $lineDeductions, 4));
        $this->assertSame(0, bccomp($totals['total_employer_contributions'], $lineEmployer, 4));
        $this->assertSame(0, bccomp($totals['net_pay'], bcsub($lineGross, $lineDeductions, 4), 4));

        $payslip = $this->payslips->generateForItem($item);

        $this->assertInstanceOf(Payslip::class, $payslip);
        $this->assertNotEmpty($payslip->payslip_number);
        $this->assertTrue(Storage::disk($payslip->disk)->exists($payslip->path));
        $this->assertStringStartsWith('PS', $payslip->payslip_number);
    }

    public function test_payslip_service_does_not_reference_journal_service(): void
    {
        $contents = file_get_contents(app_path('Services/Payroll/PayslipService.php')) ?: '';

        $this->assertStringNotContainsString('use App\\Services\\Accounting\\JournalService', $contents);
        $this->assertStringNotContainsString('use App\\Services\\Accounting\\PostingRuleEngine', $contents);
        $this->assertStringNotContainsString('use App\\Models\\ChartOfAccount', $contents);
    }

    public function test_self_service_lists_own_payslips_only_for_linked_employee(): void
    {
        $item = $this->seedCalculatedPayrollItem('28000');
        $employee = $item->employee;
        $user = User::factory()->create(['is_active' => true, 'email' => 'employee@example.com']);
        $user->assignRole('employee');
        $employee->update(['user_id' => $user->id]);

        $payslip = $this->payslips->generateForItem($item);

        $otherItem = $this->seedCalculatedPayrollItem('31000', 'EMP-OTHER');
        $this->payslips->generateForItem($otherItem);

        $service = app(EmployeeSelfServiceService::class);
        $own = $service->listOwnPayslips($user);

        $this->assertCount(1, $own);
        $this->assertSame($payslip->id, $own->first()?->id);
    }

    private function seedCalculatedPayrollItem(string $basicAmount, string $employeeCode = 'EMP-PS-1'): PayrollItem
    {
        $basic = PayComponent::query()->create([
            'code' => 'BASIC_'.$employeeCode,
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'taxable' => true,
            'account_mapping_key' => 'payroll_expense',
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);

        $structure = SalaryStructure::query()->create([
            'code' => 'STD_'.$employeeCode,
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
            'employee_code' => $employeeCode,
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'salary_structure_id' => $structure->id,
            'hire_date' => '2024-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Pay',
            'last_name' => 'Slip',
        ]);

        $run = PayrollRun::query()->create([
            'legal_entity_id' => $this->entity->id,
            'branch_id' => $this->branch->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency_code' => 'PKR',
            'status' => 'draft',
            'payroll_number' => 'PR-TEST-'.$employeeCode,
        ]);

        app(PayrollCalculationService::class)->processRun($run);

        /** @var PayrollItem $item */
        $item = PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->whereHas('employee', fn ($query) => $query->where('employee_code', $employeeCode))
            ->with(['lines', 'employee'])
            ->firstOrFail();

        return $item;
    }
}
