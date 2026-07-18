<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\HrEntitySetting;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Models\User;
use App\Services\Leave\LeaveFiscalYearService;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveAccrualTest extends TestCase
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
            'name' => 'Accrual Branch',
            'code' => 'ACR',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Accrual Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'leave', 'attendance'],
        ]);

        $this->leaveType = LeaveType::query()->create([
            'code' => 'ANNUAL-ACR-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'status' => 'active',
        ]);
    }

    public function test_fixed_annual_new_hire_grants_full_amount_without_proration(): void
    {
        $this->createPolicy(['accrual_method' => 'fixed_annual', 'accrual_rate' => 20, 'proration_on_join' => false]);
        $employee = $this->createEmployee('2026-01-15');

        $entitlement = app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $this->assertSame(20.0, (float) $entitlement->accrued_days);
    }

    public function test_fixed_annual_new_hire_is_prorated_in_calendar_year_mode(): void
    {
        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'settings_json' => ['default_leave_fiscal_year_mode' => 'calendar_year'],
        ]);
        $this->createPolicy(['accrual_method' => 'fixed_annual', 'accrual_rate' => 36.5, 'proration_on_join' => true]);
        // Hired exactly halfway through a 365-day year (2026 is not a leap year):
        // July 2nd → 183 days remaining (Jul 2 .. Dec 31 inclusive) / 365 total.
        $employee = $this->createEmployee('2026-07-02');

        $entitlement = app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $expected = round((183 / 365) * 36.5, 2);
        $this->assertSame($expected, (float) $entitlement->accrued_days);
        $this->assertLessThan(36.5, (float) $entitlement->accrued_days);
    }

    public function test_fixed_annual_new_hire_gets_full_amount_in_hire_anniversary_mode_even_with_proration(): void
    {
        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'settings_json' => ['default_leave_fiscal_year_mode' => 'hire_anniversary'],
        ]);
        $this->createPolicy(['accrual_method' => 'fixed_annual', 'accrual_rate' => 20, 'proration_on_join' => true]);
        $employee = $this->createEmployee('2026-07-02');

        $entitlement = app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $this->assertSame(20.0, (float) $entitlement->accrued_days);
    }

    public function test_fixed_annual_new_hire_is_prorated_in_fiscal_year_mode(): void
    {
        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'settings_json' => ['default_leave_fiscal_year_mode' => 'fiscal_year'],
        ]);
        FiscalYear::query()->create([
            'legal_entity_id' => $this->entity->id,
            'name' => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        $this->createPolicy(['accrual_method' => 'fixed_annual', 'accrual_rate' => 36.5, 'proration_on_join' => true]);
        $employee = $this->createEmployee('2026-07-02');

        $entitlement = app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $expected = round((183 / 365) * 36.5, 2);
        $this->assertSame($expected, (float) $entitlement->accrued_days);
    }

    public function test_monthly_accrual_grants_after_elapsed_months(): void
    {
        $this->createPolicy(['accrual_method' => 'monthly_accrual', 'accrual_rate' => 1.5]);
        $employee = $this->createEmployee('2026-01-01');
        app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $result = app(LeaveService::class)->processAccrual(CarbonImmutable::parse('2026-04-01'));

        $entitlement = LeaveEntitlement::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(4.5, (float) $entitlement->accrued_days);
        $this->assertSame('2026-04-01', $entitlement->accrual_last_run_on->toDateString());
        $this->assertSame(1, $result['processed']);
    }

    public function test_monthly_accrual_does_not_double_accrue_on_same_day_rerun(): void
    {
        $this->createPolicy(['accrual_method' => 'monthly_accrual', 'accrual_rate' => 2]);
        $employee = $this->createEmployee('2026-01-01');
        app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $service = app(LeaveService::class);
        $service->processAccrual(CarbonImmutable::parse('2026-03-01'));
        $service->processAccrual(CarbonImmutable::parse('2026-03-01'));

        $entitlement = LeaveEntitlement::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(4.0, (float) $entitlement->accrued_days);
    }

    public function test_monthly_accrual_is_capped_at_max_balance(): void
    {
        $this->createPolicy(['accrual_method' => 'monthly_accrual', 'accrual_rate' => 5, 'max_balance' => 6]);
        $employee = $this->createEmployee('2026-01-01');
        app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        app(LeaveService::class)->processAccrual(CarbonImmutable::parse('2026-04-01'));

        $entitlement = LeaveEntitlement::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(6.0, (float) $entitlement->accrued_days);
    }

    public function test_per_worked_hours_sums_only_closed_records_inside_the_window(): void
    {
        $this->createPolicy(['accrual_method' => 'per_worked_hours', 'accrual_rate' => 0.01]);
        $employee = $this->createEmployee('2026-01-01');
        app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $source = AttendanceSource::query()->create([
            'driver' => 'manual',
            'name' => 'Manual',
            'status' => 'active',
            'config_json' => [],
            'branch_id' => null,
        ]);

        // Counted: closed, inside window (480 minutes = 8 hours).
        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'branch_id' => $this->branch->id,
            'source_id' => $source->id,
            'clock_in' => '2026-02-01 09:00:00',
            'clock_out' => '2026-02-01 17:00:00',
            'worked_minutes' => 480,
            'status' => 'closed',
        ]);

        // Excluded: still open.
        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'branch_id' => $this->branch->id,
            'source_id' => $source->id,
            'clock_in' => '2026-02-02 09:00:00',
            'clock_out' => null,
            'worked_minutes' => 0,
            'status' => 'open',
        ]);

        // Excluded: after the as-of window.
        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'branch_id' => $this->branch->id,
            'source_id' => $source->id,
            'clock_in' => '2026-04-01 09:00:00',
            'clock_out' => '2026-04-01 17:00:00',
            'worked_minutes' => 480,
            'status' => 'closed',
        ]);

        app(LeaveService::class)->processAccrual(CarbonImmutable::parse('2026-03-01'));

        $entitlement = LeaveEntitlement::query()->where('employee_id', $employee->id)->firstOrFail();
        // 8 hours * 0.01 days/hour = 0.08 days.
        $this->assertSame(0.08, (float) $entitlement->accrued_days);
    }

    public function test_year_end_regrants_fixed_annual_after_reset(): void
    {
        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'settings_json' => ['default_leave_fiscal_year_mode' => 'calendar_year'],
        ]);
        $this->createPolicy(['accrual_method' => 'fixed_annual', 'accrual_rate' => 20, 'proration_on_join' => false]);
        $employee = $this->createEmployee('2025-01-01');

        $entitlement = app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);
        $entitlement->update(['used_days' => 5]);

        app(LeaveFiscalYearService::class)->processDue(CarbonImmutable::parse('2026-01-01'));

        $entitlement->refresh();
        $this->assertSame(20.0, (float) $entitlement->accrued_days);
        $this->assertSame(0.0, (float) $entitlement->used_days);
    }

    public function test_hr_manager_can_view_and_adjust_entitlement_with_audit_trail(): void
    {
        $this->createPolicy(['accrual_method' => 'fixed_annual', 'accrual_rate' => 20]);
        $employee = $this->createEmployee('2026-01-01');
        $entitlement = app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('hr-manager');

        $this->actingAs($admin)
            ->withSession(['branch_id' => $this->branch->id])
            ->get(route('admin.leave.entitlements.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['branch_id' => $this->branch->id])
            ->put(route('admin.leave.entitlements.update', $entitlement->id), [
                'accrued_days' => 25,
                'carried_forward_days' => 3,
            ])
            ->assertRedirect();

        $entitlement->refresh();
        $this->assertSame(25.0, (float) $entitlement->accrued_days);
        $this->assertSame(3.0, (float) $entitlement->carried_forward_days);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => LeaveEntitlement::class,
            'auditable_id' => $entitlement->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_user_without_permission_cannot_adjust_entitlement(): void
    {
        $this->createPolicy(['accrual_method' => 'fixed_annual', 'accrual_rate' => 20]);
        $employee = $this->createEmployee('2026-01-01');
        $entitlement = app(LeaveService::class)->resolveEntitlement($employee, $this->leaveType);

        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole('cashier');

        // Generalized 403 handler redirects with a flash error instead of a raw 403 page.
        $this->actingAs($cashier)
            ->withSession(['branch_id' => $this->branch->id])
            ->put(route('admin.leave.entitlements.update', $entitlement->id), [
                'accrued_days' => 99,
                'carried_forward_days' => 0,
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');

        $entitlement->refresh();
        $this->assertSame(20.0, (float) $entitlement->accrued_days);
    }

    private function createPolicy(array $overrides = []): LeavePolicy
    {
        return LeavePolicy::query()->create(array_merge([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 20,
            'exclude_public_holidays' => false,
            'exclude_weekends' => false,
            'effective_from' => '2020-01-01',
            'status' => 'active',
        ], $overrides));
    }

    private function createEmployee(string $hireDate): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-ACR-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => $hireDate,
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Accrual',
            'last_name' => 'Test',
            'email' => 'accrual-'.uniqid().'@example.com',
        ]);
    }
}
