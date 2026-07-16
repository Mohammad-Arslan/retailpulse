<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\DTOs\Hr\CreateEmployeeData;
use App\DTOs\Hr\TerminateEmployeeData;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
use App\Models\EmployeeBankAccount;
use App\Models\HolidayCalendar;
use App\Models\HolidayCalendarAssignment;
use App\Models\HrEntitySetting;
use App\Models\OrganizationEntity;
use App\Models\PayComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\User;
use App\Services\Hr\ApprovalApproverResolver;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\HolidayCalendarService;
use App\Services\Payroll\PayrollCalculationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave1GapsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private User $admin;

    private OrganizationEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Wave1 Gaps Branch',
            'code' => 'W1GB',
            'currency' => 'PKR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Wave1 Gaps Entity',
            'functional_currency_code' => 'PKR',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'payroll', 'employee_self_service'],
        ]);
    }

    // -------------------------------------------------------------------------
    // B1 — Bank account encryption
    // -------------------------------------------------------------------------

    public function test_employee_bank_account_encrypts_account_number_and_iban(): void
    {
        $employee = $this->createEmployee('EMP-BANK-1');

        $bank = $employee->bankAccounts()->create([
            'label' => 'Salary Account',
            'bank_name' => 'Test Bank',
            'account_number' => '1234567890',
            'iban' => 'PK00TEST0000001234567890',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $raw = DB::table('employee_bank_accounts')->where('id', $bank->id)->first();

        $this->assertNotSame('1234567890', $raw->account_number);
        $this->assertNotSame('PK00TEST0000001234567890', $raw->iban);
        $this->assertStringNotContainsString('1234567890', $raw->account_number);

        $reloaded = EmployeeBankAccount::query()->find($bank->id);
        $this->assertSame('1234567890', $reloaded->account_number);
        $this->assertSame('PK00TEST0000001234567890', $reloaded->iban);
    }

    // -------------------------------------------------------------------------
    // B2 — Terminate / reactivate
    // -------------------------------------------------------------------------

    public function test_terminate_guards_against_double_termination_and_reactivate_restores_employee(): void
    {
        $employee = $this->createEmployee('EMP-TERM-1');
        /** @var EmployeeService $service */
        $service = app(EmployeeService::class);

        $terminated = $service->terminate($employee, new TerminateEmployeeData(terminationDate: '2026-02-01'));
        $this->assertSame('terminated', $terminated->status);
        $this->assertSame('2026-02-01', $terminated->termination_date?->toDateString());

        $this->expectException(DomainException::class);
        $service->terminate($terminated, new TerminateEmployeeData(terminationDate: '2026-02-02'));
    }

    public function test_reactivate_clears_termination_date_and_restores_active_status(): void
    {
        $employee = $this->createEmployee('EMP-TERM-2');
        /** @var EmployeeService $service */
        $service = app(EmployeeService::class);

        $service->terminate($employee, new TerminateEmployeeData(terminationDate: '2026-02-01'));
        $reactivated = $service->reactivate($employee->fresh());

        $this->assertSame('active', $reactivated->status);
        $this->assertNull($reactivated->termination_date);
    }

    public function test_reactivate_rejects_non_terminated_employee(): void
    {
        $employee = $this->createEmployee('EMP-TERM-3');
        /** @var EmployeeService $service */
        $service = app(EmployeeService::class);

        $this->expectException(DomainException::class);
        $service->reactivate($employee);
    }

    public function test_terminated_employee_is_excluded_from_payroll_generation(): void
    {
        $structure = $this->createSalaryStructure('WAVE1-STD');
        $active = $this->createEmployee('EMP-ACTIVE-1', $structure->id);
        $terminated = $this->createEmployee('EMP-TERMINATED-1', $structure->id);

        app(EmployeeService::class)->terminate($terminated, new TerminateEmployeeData(terminationDate: '2026-01-01'));

        $run = PayrollRun::query()->create([
            'legal_entity_id' => $this->entity->id,
            'branch_id' => $this->branch->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency_code' => 'PKR',
            'status' => 'draft',
        ]);

        app(PayrollCalculationService::class)->processRun($run);

        $employeeIds = PayrollItem::query()->where('payroll_run_id', $run->id)->pluck('employee_id')->all();

        $this->assertContains($active->id, $employeeIds);
        $this->assertNotContains($terminated->id, $employeeIds);
    }

    // -------------------------------------------------------------------------
    // B4 — Baseline org-assignment history on create
    // -------------------------------------------------------------------------

    public function test_employee_create_records_baseline_org_assignment_history(): void
    {
        $department = Department::query()->create([
            'legal_entity_id' => $this->entity->id,
            'code' => 'DEPT-BASE',
            'name' => 'Baseline Dept',
            'status' => 'active',
        ]);

        $data = new CreateEmployeeData(
            employee: [
                'first_name' => 'Baseline',
                'last_name' => 'Employee',
                'legal_entity_id' => $this->entity->id,
                'primary_branch_id' => $this->branch->id,
                'department_id' => $department->id,
                'hire_date' => '2026-03-01',
                'employment_type' => 'full_time',
                'status' => 'active',
            ],
            profile: null,
            shift: null,
            medical: null,
            dependents: [],
            bankAccounts: [],
            branchAssignments: [],
            holidayCalendarId: null,
            imageUploads: [],
            removeImageIds: [],
        );

        $employee = app(EmployeeService::class)->create($data);

        $history = EmployeeAssignmentHistory::query()
            ->where('employee_id', $employee->id)
            ->where('field_name', 'department_id')
            ->first();

        $this->assertNotNull($history, 'Baseline department_id history row must exist on create.');
        $this->assertNull($history->old_value);
        $this->assertSame((string) $department->id, $history->new_value);
        $this->assertSame('2026-03-01', $history->effective_from?->toDateString());
    }

    // -------------------------------------------------------------------------
    // B5 — Department head resolution
    // -------------------------------------------------------------------------

    public function test_department_head_resolves_own_department_then_falls_back_to_parent_chain(): void
    {
        $head = $this->createEmployee('EMP-HEAD-1');

        $parent = Department::query()->create([
            'legal_entity_id' => $this->entity->id,
            'code' => 'DEPT-PARENT',
            'name' => 'Parent Dept',
            'head_employee_id' => $head->id,
            'status' => 'active',
        ]);

        $child = Department::query()->create([
            'legal_entity_id' => $this->entity->id,
            'code' => 'DEPT-CHILD',
            'name' => 'Child Dept',
            'parent_id' => $parent->id,
            'head_employee_id' => null,
            'status' => 'active',
        ]);

        $staff = $this->createEmployee('EMP-STAFF-1');
        $staff->update(['department_id' => $child->id]);

        $resolver = app(ApprovalApproverResolver::class);
        $resolved = $resolver->resolve('department_head', $staff->fresh());

        $this->assertNotNull($resolved);
        $this->assertSame($head->id, $resolved->id);
    }

    public function test_department_head_returns_null_when_no_head_anywhere_in_chain(): void
    {
        $department = Department::query()->create([
            'legal_entity_id' => $this->entity->id,
            'code' => 'DEPT-NOHEAD',
            'name' => 'No Head Dept',
            'status' => 'active',
        ]);

        $staff = $this->createEmployee('EMP-STAFF-2');
        $staff->update(['department_id' => $department->id]);

        $resolver = app(ApprovalApproverResolver::class);
        $this->assertNull($resolver->resolve('department_head', $staff->fresh()));
    }

    // -------------------------------------------------------------------------
    // B6 — Holiday calendar default fallback tier
    // -------------------------------------------------------------------------

    public function test_holiday_calendar_resolution_prefers_employee_over_branch_over_entity_over_default(): void
    {
        $employee = $this->createEmployee('EMP-HOL-1');

        $employeeCal = HolidayCalendar::query()->create(['code' => 'CAL-EMP', 'name' => 'Employee Cal', 'status' => 'active']);
        $branchCal = HolidayCalendar::query()->create(['code' => 'CAL-BR', 'name' => 'Branch Cal', 'status' => 'active']);
        $entityCal = HolidayCalendar::query()->create(['code' => 'CAL-ENT', 'name' => 'Entity Cal', 'status' => 'active']);
        $defaultCal = HolidayCalendar::query()->create(['code' => 'CAL-DEF', 'name' => 'Default Cal', 'status' => 'active']);

        HolidayCalendarAssignment::query()->create([
            'holiday_calendar_id' => $employeeCal->id,
            'assignable_type' => Employee::class,
            'assignable_id' => $employee->id,
            'effective_from' => '2026-01-01',
            'priority' => 100,
            'status' => 'active',
        ]);
        HolidayCalendarAssignment::query()->create([
            'holiday_calendar_id' => $branchCal->id,
            'assignable_type' => Branch::class,
            'assignable_id' => $this->branch->id,
            'effective_from' => '2026-01-01',
            'priority' => 50,
            'status' => 'active',
        ]);
        HolidayCalendarAssignment::query()->create([
            'holiday_calendar_id' => $entityCal->id,
            'assignable_type' => OrganizationEntity::class,
            'assignable_id' => $this->entity->id,
            'effective_from' => '2026-01-01',
            'priority' => 10,
            'status' => 'active',
        ]);
        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'default_holiday_calendar_id' => $defaultCal->id,
        ]);

        $resolved = app(HolidayCalendarService::class)->resolveCalendarsForEmployee($employee);
        $calendarIds = array_map(fn (array $r) => $r['calendar']->id, $resolved);

        $this->assertSame($employeeCal->id, $calendarIds[0], 'Employee-level assignment must win.');
        $this->assertSame([$employeeCal->id, $branchCal->id, $entityCal->id], $calendarIds);
        $this->assertNotContains($defaultCal->id, $calendarIds, 'Default must not apply when a specific assignment exists.');
    }

    public function test_holiday_calendar_falls_back_to_entity_default_when_no_assignment_matches(): void
    {
        $employee = $this->createEmployee('EMP-HOL-2');
        $defaultCal = HolidayCalendar::query()->create(['code' => 'CAL-DEF2', 'name' => 'Default Cal 2', 'status' => 'active']);

        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'default_holiday_calendar_id' => $defaultCal->id,
        ]);

        $resolved = app(HolidayCalendarService::class)->resolveCalendarsForEmployee($employee);

        $this->assertCount(1, $resolved);
        $this->assertSame($defaultCal->id, $resolved[0]['calendar']->id);
        $this->assertSame(1, $resolved[0]['priority']);
    }

    // -------------------------------------------------------------------------
    // F1 — Payroll run detail screen + payslip actions reachable
    // -------------------------------------------------------------------------

    public function test_payroll_run_show_and_payslip_actions_are_reachable(): void
    {
        Storage::fake('local');
        config(['payroll.payslips_disk' => 'local']);

        $item = $this->seedCalculatedPayrollItem('40000');
        $run = $item->payrollRun;

        $this->actingAs($this->admin)
            ->withSession(['branch_id' => $this->branch->id])
            ->get(route('admin.payroll.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Payroll/Runs/Show')
                ->where('run.id', $run->id)
                ->has('items', 1)
            );

        $this->actingAs($this->admin)
            ->withSession(['branch_id' => $this->branch->id])
            ->get(route('admin.payroll.items.payslip.download', $item))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->admin)
            ->withSession(['branch_id' => $this->branch->id])
            ->post(route('admin.payroll.items.payslip.generate', $item))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createEmployee(string $code, ?int $salaryStructureId = null): Employee
    {
        return Employee::query()->create([
            'employee_code' => $code,
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'salary_structure_id' => $salaryStructureId,
            'hire_date' => '2024-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => $code,
        ]);
    }

    private function createSalaryStructure(string $code, string $basicAmount = '40000'): SalaryStructure
    {
        $basic = PayComponent::query()->create([
            'code' => 'BASIC_'.$code,
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'taxable' => true,
            'account_mapping_key' => 'payroll_expense',
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);

        $structure = SalaryStructure::query()->create([
            'code' => $code,
            'name' => $code,
            'legal_entity_id' => $this->entity->id,
            'status' => 'active',
        ]);

        SalaryStructureComponent::query()->create([
            'salary_structure_id' => $structure->id,
            'pay_component_id' => $basic->id,
            'amount_or_rate' => $basicAmount,
            'sequence' => 10,
        ]);

        return $structure;
    }

    private function seedCalculatedPayrollItem(string $basicAmount): PayrollItem
    {
        $structure = $this->createSalaryStructure('WAVE1-PS-'.$basicAmount, $basicAmount);
        $employee = $this->createEmployee('EMP-PS-'.$basicAmount, $structure->id);

        $run = PayrollRun::query()->create([
            'legal_entity_id' => $this->entity->id,
            'branch_id' => $this->branch->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'currency_code' => 'PKR',
            'status' => 'draft',
            'payroll_number' => 'PR-WAVE1-'.$employee->employee_code,
        ]);

        app(PayrollCalculationService::class)->processRun($run);

        /** @var PayrollItem $item */
        $item = PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->with(['lines', 'employee', 'payrollRun'])
            ->firstOrFail();

        return $item;
    }
}
