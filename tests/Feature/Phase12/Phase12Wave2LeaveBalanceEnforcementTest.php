<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRecord;
use App\Models\ToilClaim;
use App\Models\User;
use App\Services\Leave\LeaveService;
use App\Services\Overtime\ToilLedgerService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveBalanceEnforcementTest extends TestCase
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
            'name' => 'Leave Balance Branch',
            'code' => 'LBB1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Leave Balance Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'overtime', 'leave'],
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');
    }

    public function test_block_policy_rejects_a_request_exceeding_balance_and_creates_no_row(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $this->createPolicy($leaveType, 'block');
        $this->createEntitlement($employee, $leaveType, accrued: 2.0);

        $service = app(LeaveService::class);

        try {
            $service->requestLeave(
                employee: $employee,
                leaveType: $leaveType,
                startDate: CarbonImmutable::parse('2026-08-03'),
                endDate: CarbonImmutable::parse('2026-08-05'),
            );
            $this->fail('Expected a ValidationException when the request exceeds the available balance under a block policy.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('days', $e->errors());
        }

        $this->assertSame(0, LeaveRequest::query()->count());
    }

    public function test_warn_policy_creates_the_request_flagged(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $this->createPolicy($leaveType, 'warn');
        $this->createEntitlement($employee, $leaveType, accrued: 2.0);

        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-05'),
        );

        $this->assertSame('pending', $request->status);
        $this->assertSame(3.0, (float) $request->days);
        $this->assertTrue($request->balance_warning);
    }

    public function test_allow_policy_creates_the_request_without_a_flag(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $this->createPolicy($leaveType, 'allow');
        $this->createEntitlement($employee, $leaveType, accrued: 2.0);

        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-05'),
        );

        $this->assertSame('pending', $request->status);
        $this->assertFalse($request->balance_warning);
    }

    public function test_no_matching_policy_skips_enforcement_entirely(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        // Deliberately no LeavePolicy row for this leave type.

        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-04'),
        );

        $this->assertSame('pending', $request->status);
        $this->assertFalse($request->balance_warning);
    }

    public function test_approval_time_recheck_blocks_when_a_prior_approval_already_consumed_the_balance(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $this->createPolicy($leaveType, 'block');
        $this->createEntitlement($employee, $leaveType, accrued: 5.0);

        $service = app(LeaveService::class);

        // Both requests are individually within the (still-unmoved) 5-day balance at submission time.
        $requestA = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-05'),
        );
        $requestB = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-10'),
            endDate: CarbonImmutable::parse('2026-08-12'),
        );

        $this->assertFalse($requestA->balance_warning);
        $this->assertFalse($requestB->balance_warning);

        $service->approve($requestA, $this->admin->id);

        try {
            $service->approve($requestB, $this->admin->id);
            $this->fail('Expected a DomainException when approving would push the balance negative.');
        } catch (DomainException $e) {
            // expected
        }

        $requestB->refresh();
        $this->assertSame('pending', $requestB->status, 'A blocked approval must not change the request status.');

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->firstOrFail();
        $this->assertSame(3.0, (float) $entitlement->used_days, 'Only the first, successful approval may have touched used_days.');
    }

    public function test_approval_time_recheck_flags_a_warn_policy_and_still_approves(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();
        $this->createPolicy($leaveType, 'warn');
        $this->createEntitlement($employee, $leaveType, accrued: 5.0);

        $service = app(LeaveService::class);

        $requestA = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-05'),
        );
        $requestB = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-10'),
            endDate: CarbonImmutable::parse('2026-08-12'),
        );

        $this->assertFalse($requestB->balance_warning, 'Balance was still intact at submission time.');

        $service->approve($requestA, $this->admin->id);
        $approvedB = $service->approve($requestB, $this->admin->id);

        $this->assertSame('approved', $approvedB->status);
        $this->assertTrue($approvedB->balance_warning, 'Warn policy must flag the request once approval pushes the balance negative.');

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->firstOrFail();
        $this->assertSame(6.0, (float) $entitlement->used_days);
    }

    public function test_toil_leave_is_unaffected_by_a_strict_negative_balance_policy(): void
    {
        $employee = $this->createEmployee();
        $toilLeaveType = LeaveType::query()->updateOrCreate(
            ['code' => 'TOIL'],
            [
                'name' => 'Time Off In Lieu',
                'is_paid' => true,
                'affects_payroll' => false,
                'allow_leave_claim' => true,
                'allow_cash_claim' => true,
                'status' => 'active',
            ],
        );
        // Deliberately strict, to prove TOIL never consults this policy at all.
        $this->createPolicy($toilLeaveType, 'block');

        $policy = OvertimePolicy::query()->create([
            'daily_threshold_minutes' => 480,
            'rest_day_applies' => true,
            'public_holiday_applies' => false,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'priority' => 100,
        ]);
        $record = OvertimeRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-07-11',
            'regular_minutes' => 480,
            'overtime_minutes' => 8 * 60,
            'day_type' => 'rest_day',
            'resolved_multiplier' => 1.0,
            'overtime_policy_id' => $policy->id,
            'status' => 'pending',
        ]);
        app(ToilLedgerService::class)->credit($employee, $record, 8.0, null);

        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $this->assertSame('pending', $request->status);
        $this->assertFalse(
            $request->balance_warning,
            'TOIL must never be routed through the entitlement-based balance check.',
        );
        $this->assertSame(
            0,
            LeaveEntitlement::query()->where('employee_id', $employee->id)->where('leave_type_id', $toilLeaveType->id)->count(),
            'TOIL draws from the TOIL ledger, not a LeaveEntitlement — none should ever be created for it.',
        );

        $approved = $service->approve($request, $this->admin->id);
        $this->assertSame('approved', $approved->status);

        $claim = ToilClaim::query()->where('leave_request_id', $request->id)->firstOrFail();
        $this->assertSame('approved', $claim->status);
    }

    private function createEmployee(): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-LBB-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Sana',
            'last_name' => 'Malik',
            'email' => 'sana-'.uniqid().'@example.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLeaveType(array $overrides = []): LeaveType
    {
        return LeaveType::query()->create(array_merge([
            'code' => 'ANNUAL-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'payroll_deduction_component_code' => null,
            'status' => 'active',
        ], $overrides));
    }

    private function createPolicy(LeaveType $leaveType, string $negativeLeaveBalancePolicy): LeavePolicy
    {
        return LeavePolicy::query()->create([
            'leave_type_id' => $leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'monthly_accrual',
            'accrual_rate' => 0,
            'max_balance' => null,
            'carry_forward_limit' => null,
            'carry_forward_expiry_months' => null,
            'negative_leave_balance_policy' => $negativeLeaveBalancePolicy,
            'proration_on_join' => false,
            'exclude_public_holidays' => false,
            'exclude_weekends' => false,
            'short_leave_max_hours' => null,
            'short_leave_max_requests_per_month' => null,
            'out_station_deducts_balance' => false,
            'encashment_allowed' => false,
            'encashment_max_days' => null,
            'encashment_requires_approval' => true,
            'year_end_excess_disposition' => 'expire',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => 'active',
        ]);
    }

    private function createEntitlement(Employee $employee, LeaveType $leaveType, float $accrued): LeaveEntitlement
    {
        return LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => $accrued,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);
    }
}
