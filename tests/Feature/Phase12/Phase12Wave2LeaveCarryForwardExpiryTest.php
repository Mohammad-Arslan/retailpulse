<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\LeaveYearEndRun;
use App\Models\OrganizationEntity;
use App\Services\Leave\LeaveFiscalYearService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveCarryForwardExpiryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private OrganizationEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'CF Expiry Branch',
            'code' => 'CFEX',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'CF Expiry Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'leave'],
        ]);
    }

    public function test_close_entitlement_stamps_expiry_when_policy_has_a_window(): void
    {
        $leaveType = $this->createLeaveType();
        LeavePolicy::query()->create([
            'leave_type_id' => $leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'carry_forward_limit' => 5,
            'carry_forward_expiry_months' => 3,
            'year_end_excess_disposition' => 'expire',
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee();
        $entitlement = LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 20,
            'used_days' => 5,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);
        // remaining = 15; carry_forward_limit = 5 -> 5 days carried, stamped to expire 3 months out.

        $service = app(LeaveFiscalYearService::class);
        $service->processDue(CarbonImmutable::parse('2027-01-01'));

        $entitlement->refresh();
        $this->assertSame(5.0, (float) $entitlement->carried_forward_days);
        $this->assertNotNull($entitlement->carried_forward_expires_at);
        $this->assertSame('2027-04-01', $entitlement->carried_forward_expires_at->toDateString());
    }

    public function test_close_entitlement_leaves_expiry_null_when_policy_has_no_window(): void
    {
        $leaveType = $this->createLeaveType();
        LeavePolicy::query()->create([
            'leave_type_id' => $leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'carry_forward_limit' => 5,
            'carry_forward_expiry_months' => null,
            'year_end_excess_disposition' => 'expire',
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee();
        $entitlement = LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 20,
            'used_days' => 5,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);

        $service = app(LeaveFiscalYearService::class);
        $service->processDue(CarbonImmutable::parse('2027-01-01'));

        $entitlement->refresh();
        $this->assertSame(5.0, (float) $entitlement->carried_forward_days);
        $this->assertNull($entitlement->carried_forward_expires_at, 'No expiry window configured must behave exactly as before this change.');
    }

    public function test_expiry_is_a_no_op_before_the_stamped_date_and_reduces_after_it(): void
    {
        $leaveType = $this->createLeaveType();
        $employee = $this->createEmployee();
        $entitlement = LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 0,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 5,
            'carried_forward_expires_at' => '2027-04-01',
        ]);

        $service = app(LeaveFiscalYearService::class);

        $before = $service->expireDueCarriedForward(CarbonImmutable::parse('2027-03-01'));
        $this->assertSame([], $before);
        $entitlement->refresh();
        $this->assertSame(5.0, (float) $entitlement->carried_forward_days);
        $this->assertNotNull($entitlement->carried_forward_expires_at);

        $runs = $service->expireDueCarriedForward(CarbonImmutable::parse('2027-04-01'));
        $this->assertCount(1, $runs);
        $this->assertEqualsWithDelta(5.0, $runs[0]->totals_json['expired'], 0.001);

        $entitlement->refresh();
        $this->assertSame(0.0, (float) $entitlement->carried_forward_days);
        $this->assertNull($entitlement->carried_forward_expires_at);
    }

    public function test_only_the_unused_remainder_of_carried_forward_expires(): void
    {
        $leaveType = $this->createLeaveType();
        $employee = $this->createEmployee();
        $entitlement = LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 0,
            'used_days' => 3,
            'encashed_days' => 0,
            'carried_forward_days' => 5,
            'carried_forward_expires_at' => '2027-04-01',
        ]);
        // remaining = 0 + 5 - 3 - 0 = 2 -> only 2 of the 5 carried-forward days are still unused.

        $service = app(LeaveFiscalYearService::class);
        $runs = $service->expireDueCarriedForward(CarbonImmutable::parse('2027-04-01'));

        $this->assertCount(1, $runs);
        $this->assertEqualsWithDelta(2.0, $runs[0]->totals_json['expired'], 0.001);

        $entitlement->refresh();
        $this->assertSame(3.0, (float) $entitlement->carried_forward_days, 'Only the 2 unused days should expire, leaving 3.');
        $this->assertSame(3.0, (float) $entitlement->used_days, 'Expiry must never touch used_days.');
    }

    public function test_running_expiry_twice_for_the_same_date_is_idempotent(): void
    {
        $leaveType = $this->createLeaveType();
        $employee = $this->createEmployee();
        LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 0,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 5,
            'carried_forward_expires_at' => '2027-04-01',
        ]);

        $service = app(LeaveFiscalYearService::class);
        $first = $service->expireDueCarriedForward(CarbonImmutable::parse('2027-04-01'));
        $second = $service->expireDueCarriedForward(CarbonImmutable::parse('2027-04-01'));

        $this->assertCount(1, $first);
        $this->assertCount(0, $second, 'A second run for the same as-of date must be a no-op.');
        $this->assertSame(1, LeaveYearEndRun::query()->where('period_label', 'CF-EXPIRY-2027-04-01')->count());
    }

    public function test_process_year_end_command_runs_both_year_end_close_and_carry_forward_expiry(): void
    {
        // Leave type A: an active policy, so the calendar-year close actually fires for it.
        $leaveTypeA = $this->createLeaveType(['code' => 'ANNUAL-CFCMD-A-'.uniqid()]);
        LeavePolicy::query()->create([
            'leave_type_id' => $leaveTypeA->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'carry_forward_limit' => null,
            'carry_forward_expiry_months' => null,
            'year_end_excess_disposition' => 'expire',
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);
        $employeeA = $this->createEmployee();
        LeaveEntitlement::query()->create([
            'employee_id' => $employeeA->id,
            'leave_type_id' => $leaveTypeA->id,
            'fiscal_year_id' => null,
            'accrued_days' => 10,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);

        // Leave type B: deliberately no LeavePolicy, so the year-end close skips it entirely
        // (closeEntitlement() bails when resolveLeavePolicy() returns null) — this isolates
        // the carry-forward-expiry step, which doesn't consult the policy at all.
        $leaveTypeB = $this->createLeaveType(['code' => 'ANNUAL-CFCMD-B-'.uniqid()]);
        $employeeB = $this->createEmployee();
        $entitlementB = LeaveEntitlement::query()->create([
            'employee_id' => $employeeB->id,
            'leave_type_id' => $leaveTypeB->id,
            'fiscal_year_id' => null,
            'accrued_days' => 0,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 4,
            'carried_forward_expires_at' => '2026-12-01',
        ]);

        $exitCode = Artisan::call('leave:process-year-end', ['--as-of' => '2027-01-01']);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, LeaveYearEndRun::query()->where('period_label', '2026')->count());
        $this->assertSame(1, LeaveYearEndRun::query()->where('period_label', 'CF-EXPIRY-2027-01-01')->count());

        $entitlementB->refresh();
        $this->assertSame(0.0, (float) $entitlementB->carried_forward_days);
    }

    private function createEmployee(): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-CFEX-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Bilal',
            'last_name' => 'Chaudhry',
            'email' => 'bilal-'.uniqid().'@example.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLeaveType(array $overrides = []): LeaveType
    {
        return LeaveType::query()->create(array_merge([
            'code' => 'ANNUAL-CFEX-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'status' => 'active',
        ], $overrides));
    }
}
