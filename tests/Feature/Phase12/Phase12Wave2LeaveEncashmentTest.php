<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Models\PayComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\User;
use App\Services\Leave\LeaveEncashmentService;
use App\Services\Payroll\PayrollCalculationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveEncashmentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private User $admin;

    private OrganizationEntity $entity;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Encashment Branch',
            'code' => 'ENCB',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Encashment Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'leave', 'payroll'],
        ]);

        $this->leaveType = LeaveType::query()->create([
            'code' => 'ANNUAL-ENC-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'payroll_encashment_component_code' => 'LEAVE_ENCASHMENT',
            'status' => 'active',
        ]);
    }

    public function test_encashment_is_rejected_when_policy_does_not_allow_it(): void
    {
        $this->createPolicy(['encashment_allowed' => false]);
        $employee = $this->createEmployee();
        $this->createEntitlement($employee, 20);

        $service = app(LeaveEncashmentService::class);

        try {
            $service->requestEncashment($employee, $this->leaveType, 5);
            $this->fail('Expected a ValidationException when encashment is not allowed by policy.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('leave_type_id', $e->errors());
        }
    }

    public function test_encashment_exceeding_policy_max_days_is_rejected(): void
    {
        $this->createPolicy(['encashment_allowed' => true, 'encashment_max_days' => 5]);
        $employee = $this->createEmployee();
        $this->createEntitlement($employee, 20);

        $service = app(LeaveEncashmentService::class);

        try {
            $service->requestEncashment($employee, $this->leaveType, 10);
            $this->fail('Expected a ValidationException when exceeding encashment_max_days.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('days', $e->errors());
            $this->assertStringContainsString('5', $e->errors()['days'][0]);
        }
    }

    public function test_encashment_exceeding_available_balance_is_rejected(): void
    {
        $this->createPolicy(['encashment_allowed' => true]);
        $employee = $this->createEmployee();
        $this->createEntitlement($employee, 3);

        $service = app(LeaveEncashmentService::class);

        try {
            $service->requestEncashment($employee, $this->leaveType, 5);
            $this->fail('Expected a ValidationException when exceeding the available balance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('days', $e->errors());
        }
    }

    public function test_encashment_without_configured_component_throws_domain_exception(): void
    {
        $this->leaveType->update(['payroll_encashment_component_code' => null]);
        $this->createPolicy(['encashment_allowed' => true]);
        $employee = $this->createEmployee();
        $this->createEntitlement($employee, 20);

        $service = app(LeaveEncashmentService::class);

        $this->expectException(DomainException::class);
        $service->requestEncashment($employee, $this->leaveType, 5);
    }

    public function test_approval_reduces_remaining_balance_via_encashed_days_not_used_days(): void
    {
        $this->createPolicy(['encashment_allowed' => true]);
        $employee = $this->createEmployee();
        $this->createEntitlement($employee, 20);

        $service = app(LeaveEncashmentService::class);
        $encashment = $service->requestEncashment($employee, $this->leaveType, 5, 'Cash out unused leave');

        $this->assertSame('pending', $encashment->status);

        $approved = $service->approve($encashment, $this->admin->id);
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->firstOrFail();

        $this->assertSame(0.0, (float) $entitlement->used_days, 'Encashment must never touch used_days.');
        $this->assertSame(5.0, (float) $entitlement->encashed_days);
        $this->assertSame(15.0, (float) $entitlement->remaining_days);
    }

    public function test_reject_does_not_touch_balance(): void
    {
        $this->createPolicy(['encashment_allowed' => true]);
        $employee = $this->createEmployee();
        $this->createEntitlement($employee, 20);

        $service = app(LeaveEncashmentService::class);
        $encashment = $service->requestEncashment($employee, $this->leaveType, 5);

        $service->reject($encashment, $this->admin->id, 'Not eligible this cycle');

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->firstOrFail();

        $this->assertSame(0.0, (float) $entitlement->encashed_days);
        $this->assertSame(20.0, (float) $entitlement->remaining_days);
    }

    public function test_cancel_of_approved_encashment_releases_encashed_days(): void
    {
        $this->createPolicy(['encashment_allowed' => true]);
        $employee = $this->createEmployee();
        $this->createEntitlement($employee, 20);

        $service = app(LeaveEncashmentService::class);
        $encashment = $service->requestEncashment($employee, $this->leaveType, 5);
        $approved = $service->approve($encashment, $this->admin->id);

        $service->cancel($approved, $this->admin->id);

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->firstOrFail();

        $this->assertSame(0.0, (float) $entitlement->encashed_days, 'Cancelling an approved encashment must release the balance back.');
        $this->assertSame(20.0, (float) $entitlement->remaining_days);
    }

    public function test_policy_without_approval_requirement_auto_approves_immediately(): void
    {
        $this->createPolicy(['encashment_allowed' => true, 'encashment_requires_approval' => false]);
        $employee = $this->createEmployee();
        $this->createEntitlement($employee, 20);

        $service = app(LeaveEncashmentService::class);
        $encashment = $service->requestEncashment($employee, $this->leaveType, 5);

        $this->assertSame('approved', $encashment->status);
        $this->assertNotNull($encashment->approved_at);

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->firstOrFail();

        $this->assertSame(5.0, (float) $entitlement->encashed_days);
    }

    public function test_approved_encashment_feeds_payroll_as_earning_via_basis_daily_rate(): void
    {
        $this->createPolicy(['encashment_allowed' => true]);

        $basicComponent = PayComponent::query()->create([
            'code' => 'BASIC',
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'taxable' => true,
            'account_mapping_key' => 'payroll_expense',
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);

        $encashmentComponent = PayComponent::query()->create([
            'code' => 'LEAVE_ENCASHMENT',
            'name' => 'Leave Encashment',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'basis_component_id' => $basicComponent->id,
            'taxable' => true,
            'account_mapping_key' => 'payroll_expense',
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);

        $structure = SalaryStructure::query()->create([
            'code' => 'ENC-STANDARD',
            'name' => 'Encashment Standard',
            'legal_entity_id' => $this->entity->id,
            'status' => 'active',
        ]);

        SalaryStructureComponent::query()->create([
            'salary_structure_id' => $structure->id,
            'pay_component_id' => $basicComponent->id,
            'amount_or_rate' => '30000',
            'sequence' => 10,
        ]);

        $employee = $this->createEmployee($structure->id);
        $this->createEntitlement($employee, 20);

        $service = app(LeaveEncashmentService::class);
        $encashment = $service->requestEncashment($employee, $this->leaveType, 5);
        $service->approve($encashment, $this->admin->id);

        $run = PayrollRun::query()->create([
            'legal_entity_id' => $this->entity->id,
            'branch_id' => $this->branch->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'currency_code' => 'USD',
            'status' => 'draft',
        ]);

        $payrollService = app(PayrollCalculationService::class);
        $payrollService->processRun($run);

        $item = PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->with('lines')
            ->firstOrFail();
        $encashmentLine = $item->lines->first(
            fn ($line) => ($line->component_snapshot_json['code'] ?? null) === 'LEAVE_ENCASHMENT'
        );

        $this->assertNotNull($encashmentLine, 'Approved encashment must produce a LEAVE_ENCASHMENT payroll line.');
        // daily rate = 30000 / 30 (default payroll.leave_days_in_month) = 1000; 5 days = 5000.
        $this->assertSame(0, bccomp('5000.0000', (string) $encashmentLine->amount, 4));
    }

    private function createPolicy(array $overrides = []): LeavePolicy
    {
        return LeavePolicy::query()->create(array_merge([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ], $overrides));
    }

    private function createEmployee(?int $salaryStructureId = null): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-ENC-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'salary_structure_id' => $salaryStructureId,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Sara',
            'last_name' => 'Khan',
            'email' => 'sara-'.uniqid().'@example.com',
        ]);
    }

    private function createEntitlement(Employee $employee, float $accruedDays): LeaveEntitlement
    {
        return LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => $accruedDays,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);
    }
}
