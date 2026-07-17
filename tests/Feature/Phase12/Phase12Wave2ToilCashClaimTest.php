<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRecord;
use App\Models\PayComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\ToilBalance;
use App\Models\User;
use App\Services\Overtime\ToilClaimService;
use App\Services\Overtime\ToilLedgerService;
use App\Services\Payroll\PayrollCalculationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2ToilCashClaimTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private OrganizationEntity $entity;

    private Employee $employee;

    private User $admin;

    private LeaveType $toilLeaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Toil Cash Branch',
            'code' => 'TCC1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Toil Cash Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'overtime', 'leave', 'payroll'],
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->employee = Employee::query()->create([
            'employee_code' => 'EMP-TCC-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Sana',
            'last_name' => 'Yousaf',
            'email' => 'sana-'.uniqid().'@example.com',
        ]);

        $this->toilLeaveType = LeaveType::query()->updateOrCreate(
            ['code' => 'TOIL'],
            [
                'name' => 'Time Off In Lieu',
                'is_paid' => true,
                'affects_payroll' => false,
                'allow_leave_claim' => true,
                'allow_cash_claim' => true,
                'payroll_toil_payout_component_code' => 'TOIL_PAYOUT',
                'status' => 'active',
            ],
        );

        $this->creditToil(10.0);
    }

    public function test_cash_claim_is_rejected_when_the_leave_type_disallows_it(): void
    {
        $this->toilLeaveType->update(['allow_cash_claim' => false]);

        $service = app(ToilClaimService::class);

        try {
            $service->requestCashClaim($this->employee, $this->toilLeaveType, 4.0);
            $this->fail('Expected a ValidationException when allow_cash_claim is false.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('claim_type', $e->errors());
        }
    }

    public function test_cash_claim_without_configured_component_throws_domain_exception(): void
    {
        $this->toilLeaveType->update(['payroll_toil_payout_component_code' => null]);

        $service = app(ToilClaimService::class);

        $this->expectException(DomainException::class);
        $service->requestCashClaim($this->employee, $this->toilLeaveType, 4.0);
    }

    public function test_cash_claim_creates_no_leave_request_row(): void
    {
        $service = app(ToilClaimService::class);
        $claim = $service->requestCashClaim($this->employee, $this->toilLeaveType, 4.0, 'Payout please');

        $this->assertSame('cash', $claim->claim_type);
        $this->assertSame(0, LeaveRequest::query()->count(), 'A cash claim must never create a leave_requests row.');

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(6.0, (float) $balance->available_hours);
        $this->assertSame(4.0, (float) $balance->pending_hours);
    }

    public function test_approving_a_cash_claim_debits_the_ledger(): void
    {
        $service = app(ToilClaimService::class);
        $claim = $service->requestCashClaim($this->employee, $this->toilLeaveType, 4.0);

        $approved = $service->approve($claim, $this->admin->id);
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->approved_at);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(6.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_rejecting_a_cash_claim_releases_the_hold(): void
    {
        $service = app(ToilClaimService::class);
        $claim = $service->requestCashClaim($this->employee, $this->toilLeaveType, 4.0);

        $service->reject($claim, $this->admin->id, 'Not eligible');

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(10.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_approved_cash_claim_feeds_payroll_as_earning_via_hourly_rate(): void
    {
        $service = app(ToilClaimService::class);
        $claim = $service->requestCashClaim($this->employee, $this->toilLeaveType, 4.0);
        $service->approve($claim, $this->admin->id);

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

        PayComponent::query()->create([
            'code' => 'TOIL_PAYOUT',
            'name' => 'TOIL Payout',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'basis_component_id' => $basicComponent->id,
            'taxable' => true,
            'account_mapping_key' => 'payroll_expense',
            'effective_from' => '2024-01-01',
            'status' => 'active',
        ]);

        $structure = SalaryStructure::query()->create([
            'code' => 'TOIL-STANDARD',
            'name' => 'Toil Standard',
            'legal_entity_id' => $this->entity->id,
            'status' => 'active',
        ]);

        SalaryStructureComponent::query()->create([
            'salary_structure_id' => $structure->id,
            'pay_component_id' => $basicComponent->id,
            'amount_or_rate' => '24000', // 24000 / 30 days = 800/day; 800/8h = 100/hour.
            'sequence' => 10,
        ]);

        $employee = Employee::query()->create([
            'employee_code' => 'EMP-TCC2-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'salary_structure_id' => $structure->id,
            'hire_date' => '2026-01-01',
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'toil-payroll-'.uniqid().'@example.com',
        ]);

        // Re-run the claim flow for the payroll-linked employee.
        $this->creditToilFor($employee, 10.0);
        $claim2 = $service->requestCashClaim($employee, $this->toilLeaveType, 4.0);
        $service->approve($claim2, $this->admin->id);

        $run = PayrollRun::query()->create([
            'legal_entity_id' => $this->entity->id,
            'branch_id' => $this->branch->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'currency_code' => 'USD',
            'status' => 'draft',
        ]);

        app(PayrollCalculationService::class)->processRun($run);

        $item = PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->with('lines')
            ->firstOrFail();

        $toilLine = $item->lines->first(
            fn ($line) => ($line->component_snapshot_json['code'] ?? null) === 'TOIL_PAYOUT'
        );

        $this->assertNotNull($toilLine, 'Approved cash claim must produce a TOIL_PAYOUT payroll line.');
        // hourly rate = (24000/30)/8 = 100; 4 hours = 400.
        $this->assertSame(0, bccomp('400.0000', (string) $toilLine->amount, 4));
    }

    private function creditToil(float $hours): void
    {
        $this->creditToilFor($this->employee, $hours);
    }

    private function creditToilFor(Employee $employee, float $hours): void
    {
        $policy = OvertimePolicy::query()->firstOrCreate(
            ['daily_threshold_minutes' => 480],
            [
                'rest_day_applies' => true,
                'public_holiday_applies' => false,
                'effective_from' => '2026-01-01',
                'status' => 'active',
                'priority' => 100,
            ],
        );

        $record = OvertimeRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => now()->subDays(random_int(1, 100))->toDateString(),
            'regular_minutes' => 480,
            'overtime_minutes' => (int) ($hours * 60),
            'day_type' => 'rest_day',
            'resolved_multiplier' => 1.0,
            'overtime_policy_id' => $policy->id,
            'status' => 'pending',
        ]);

        app(ToilLedgerService::class)->credit($employee, $record, $hours, null);
    }
}
