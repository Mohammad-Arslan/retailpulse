<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\HrEntitySetting;
use App\Models\LeaveEncashment;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveYearEndRun;
use App\Models\OrganizationEntity;
use App\Services\Leave\LeaveFiscalYearService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveFiscalYearTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private OrganizationEntity $entity;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'FY Branch',
            'code' => 'FYBR',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'FY Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'leave'],
        ]);

        $this->leaveType = LeaveType::query()->create([
            'code' => 'ANNUAL-FY-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'status' => 'active',
        ]);
    }

    public function test_calendar_year_mode_carries_up_to_the_limit_and_expires_the_rest(): void
    {
        LeavePolicy::query()->create([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'carry_forward_limit' => 5,
            'year_end_excess_disposition' => 'expire',
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee();
        $entitlement = LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 20,
            'used_days' => 5,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);
        // remaining = 20 - 5 = 15; carry_forward_limit = 5 -> carry 5, expire 10.

        $service = app(LeaveFiscalYearService::class);
        $runs = $service->processDue(CarbonImmutable::parse('2027-01-01'));

        $this->assertCount(1, $runs);
        $run = $runs[0];
        $this->assertSame('2026', $run->period_label);
        $this->assertSame(1, $run->totals_json['entitlements_processed']);
        $this->assertEqualsWithDelta(5.0, $run->totals_json['carried_forward'], 0.001);
        $this->assertEqualsWithDelta(10.0, $run->totals_json['expired'], 0.001);
        $this->assertEqualsWithDelta(0.0, $run->totals_json['encashed'], 0.001);

        $entitlement->refresh();
        $this->assertSame(0.0, (float) $entitlement->accrued_days);
        $this->assertSame(0.0, (float) $entitlement->used_days);
        $this->assertSame(5.0, (float) $entitlement->carried_forward_days);
        $this->assertSame(5.0, (float) $entitlement->remaining_days);
    }

    public function test_excess_is_auto_encashed_when_policy_opts_in(): void
    {
        LeavePolicy::query()->create([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'carry_forward_limit' => 5,
            'encashment_allowed' => true,
            'year_end_excess_disposition' => 'encash',
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->leaveType->update(['payroll_encashment_component_code' => 'LEAVE_ENCASHMENT']);

        $employee = $this->createEmployee();
        LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 20,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);
        // remaining = 20; carry 5, excess 15 -> auto-encashed.

        $service = app(LeaveFiscalYearService::class);
        $runs = $service->processDue(CarbonImmutable::parse('2027-01-01'));

        $this->assertEqualsWithDelta(15.0, $runs[0]->totals_json['encashed'], 0.001);

        $encashment = LeaveEncashment::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->firstOrFail();

        $this->assertSame('approved', $encashment->status);
        $this->assertSame(15.0, (float) $encashment->days);
        $this->assertSame('LEAVE_ENCASHMENT', $encashment->payroll_component_code);
    }

    public function test_days_locked_in_a_pending_request_are_never_expired_or_encashed(): void
    {
        LeavePolicy::query()->create([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'carry_forward_limit' => 5,
            'year_end_excess_disposition' => 'expire',
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee();
        $entitlement = LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 20,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);

        // A pending (not yet approved) request holds 8 days against the balance.
        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-08',
            'duration_type' => 'full_day',
            'days' => 8,
            'deduct_from_balance' => true,
            'status' => 'pending',
        ]);

        // remaining = 20; pendingHold = 8; availableForDisposition = 12; carry_forward_limit = 5
        // -> carry 5 (under cap) + 8 (pending hold, always preserved) = 13; excess = 12 - 5 = 7 expired.
        $service = app(LeaveFiscalYearService::class);
        $runs = $service->processDue(CarbonImmutable::parse('2027-01-01'));

        $this->assertEqualsWithDelta(13.0, $runs[0]->totals_json['carried_forward'], 0.001);
        $this->assertEqualsWithDelta(7.0, $runs[0]->totals_json['expired'], 0.001);

        $entitlement->refresh();
        $this->assertSame(13.0, (float) $entitlement->carried_forward_days);
        $this->assertGreaterThanOrEqual(8.0, (float) $entitlement->remaining_days, 'The pending request must still be coverable after year-end.');
    }

    public function test_running_twice_for_the_same_period_is_idempotent(): void
    {
        LeavePolicy::query()->create([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'carry_forward_limit' => null,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee();
        LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 10,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);

        $service = app(LeaveFiscalYearService::class);
        $first = $service->processDue(CarbonImmutable::parse('2027-01-01'));
        $second = $service->processDue(CarbonImmutable::parse('2027-01-01'));

        $this->assertCount(1, $first);
        $this->assertCount(0, $second, 'A second run for the same period must be a no-op.');
        $this->assertSame(1, LeaveYearEndRun::query()->count());
    }

    public function test_hire_anniversary_mode_processes_only_on_the_employees_anniversary(): void
    {
        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'settings_json' => ['default_leave_fiscal_year_mode' => 'hire_anniversary'],
        ]);

        LeavePolicy::query()->create([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'carry_forward_limit' => null,
            'effective_from' => '2025-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee('2025-03-15');
        $entitlement = LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 10,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
        ]);

        $service = app(LeaveFiscalYearService::class);

        // Not the anniversary — no-op.
        $this->assertCount(0, $service->processDue(CarbonImmutable::parse('2027-06-01')));
        $entitlement->refresh();
        $this->assertSame(10.0, (float) $entitlement->accrued_days);

        // 2027-03-15 is the employee's second anniversary since hire (2025-03-15) — year-end processed.
        $runs = $service->processDue(CarbonImmutable::parse('2027-03-15'));
        $this->assertCount(1, $runs);
        $this->assertSame("EMP-{$employee->id}-2026", $runs[0]->period_label);

        $entitlement->refresh();
        $this->assertSame(0.0, (float) $entitlement->accrued_days);
        $this->assertSame(10.0, (float) $entitlement->carried_forward_days);
    }

    private function createEmployee(?string $hireDate = null): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-FY-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => $hireDate ?? '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Nadia',
            'last_name' => 'Iqbal',
            'email' => 'nadia-'.uniqid().'@example.com',
        ]);
    }
}
