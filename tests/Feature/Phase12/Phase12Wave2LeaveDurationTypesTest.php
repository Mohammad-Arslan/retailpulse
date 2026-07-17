<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\HrEntitySetting;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Models\User;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveDurationTypesTest extends TestCase
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
            'name' => 'Leave Branch',
            'code' => 'LEAV',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Leave Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'leave'],
        ]);

        $this->leaveType = LeaveType::query()->create([
            'code' => 'ANNUAL-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'status' => 'active',
        ]);
    }

    public function test_half_day_request_counts_as_half_a_day_and_requires_session(): void
    {
        $employee = $this->createEmployee();
        $service = app(LeaveService::class);

        $this->expectExceptionValidationField($service, function () use ($service, $employee): void {
            $service->requestLeave(
                employee: $employee,
                leaveType: $this->leaveType,
                startDate: CarbonImmutable::parse('2026-07-20'),
                endDate: CarbonImmutable::parse('2026-07-20'),
                durationType: 'half_day',
            );
        }, 'session');

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-20'),
            endDate: CarbonImmutable::parse('2026-07-20'),
            durationType: 'half_day',
            session: 'morning',
        );

        $this->assertSame(0.5, (float) $request->days);
        $this->assertSame('morning', $request->session);
        $this->assertTrue($request->deduct_from_balance);
    }

    public function test_half_day_request_spanning_two_dates_is_rejected(): void
    {
        $employee = $this->createEmployee();
        $service = app(LeaveService::class);

        try {
            $service->requestLeave(
                employee: $employee,
                leaveType: $this->leaveType,
                startDate: CarbonImmutable::parse('2026-07-20'),
                endDate: CarbonImmutable::parse('2026-07-21'),
                durationType: 'half_day',
                session: 'morning',
            );
            $this->fail('Expected a ValidationException for a multi-date half day request.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('end_date', $e->errors());
        }
    }

    public function test_short_leave_rejects_end_time_before_or_equal_to_start_time(): void
    {
        $employee = $this->createEmployee();
        $service = app(LeaveService::class);

        try {
            $service->requestLeave(
                employee: $employee,
                leaveType: $this->leaveType,
                startDate: CarbonImmutable::parse('2026-07-20'),
                endDate: CarbonImmutable::parse('2026-07-20'),
                durationType: 'short_leave',
                startTime: '10:00',
                endTime: '09:00',
            );
            $this->fail('Expected a ValidationException for end_time <= start_time.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('end_time', $e->errors());
        }
    }

    public function test_short_leave_computes_hours_over_configured_work_hours_per_day(): void
    {
        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'settings_json' => ['work_hours_per_day' => 4],
        ]);

        $employee = $this->createEmployee();
        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-20'),
            endDate: CarbonImmutable::parse('2026-07-20'),
            durationType: 'short_leave',
            startTime: '09:00',
            endTime: '11:00',
        );

        // 2 hours / 4 work-hours-per-day = 0.5 days.
        $this->assertSame(0.5, (float) $request->days);
    }

    public function test_short_leave_exceeding_max_hours_is_rejected_with_configured_limit_in_message(): void
    {
        $policy = LeavePolicy::query()->create([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'short_leave_max_hours' => 3,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee();
        $service = app(LeaveService::class);

        try {
            $service->requestLeave(
                employee: $employee,
                leaveType: $this->leaveType,
                startDate: CarbonImmutable::parse('2026-07-20'),
                endDate: CarbonImmutable::parse('2026-07-20'),
                durationType: 'short_leave',
                startTime: '09:00',
                endTime: '13:00',
            );
            $this->fail('Expected a ValidationException for exceeding short_leave_max_hours.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('end_time', $e->errors());
            $this->assertStringContainsString('3', $e->errors()['end_time'][0]);
        }

        $this->assertSame('3.00', (string) $policy->fresh()->short_leave_max_hours);
    }

    public function test_short_leave_monthly_quota_counts_pending_and_approved_requests(): void
    {
        LeavePolicy::query()->create([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'short_leave_max_requests_per_month' => 2,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $employee = $this->createEmployee();
        $service = app(LeaveService::class);

        $first = $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-05'),
            endDate: CarbonImmutable::parse('2026-07-05'),
            durationType: 'short_leave',
            startTime: '09:00',
            endTime: '10:00',
        );
        $this->assertSame('pending', $first->status);

        // Second request stays pending too — quota counts pending + approved, not just approved.
        $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-10'),
            endDate: CarbonImmutable::parse('2026-07-10'),
            durationType: 'short_leave',
            startTime: '09:00',
            endTime: '10:00',
        );

        try {
            $service->requestLeave(
                employee: $employee,
                leaveType: $this->leaveType,
                startDate: CarbonImmutable::parse('2026-07-15'),
                endDate: CarbonImmutable::parse('2026-07-15'),
                durationType: 'short_leave',
                startTime: '09:00',
                endTime: '10:00',
            );
            $this->fail('Expected a ValidationException once the monthly short-leave quota is exhausted by pending requests.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('duration_type', $e->errors());
        }
    }

    public function test_out_station_never_deducts_balance_on_approve_or_cancel(): void
    {
        LeavePolicy::query()->create([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'out_station_deducts_balance' => false,
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
            'carried_forward_days' => 0,
        ]);

        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-20'),
            endDate: CarbonImmutable::parse('2026-07-22'),
            durationType: 'out_station',
        );

        $this->assertFalse($request->deduct_from_balance);
        $this->assertSame(3.0, (float) $request->days);

        $approved = $service->approve($request, $this->admin->id);

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->firstOrFail();

        $this->assertSame(0.0, (float) $entitlement->used_days, 'Out Station approval must not touch the leave balance.');

        $service->cancel($approved, $this->admin->id);

        $entitlement->refresh();
        $this->assertSame(0.0, (float) $entitlement->used_days, 'Cancelling an approved Out Station request must not decrement a balance that was never deducted.');
    }

    public function test_full_day_leave_behaviour_is_unchanged(): void
    {
        $employee = $this->createEmployee();
        LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 10,
            'used_days' => 0,
            'carried_forward_days' => 0,
        ]);

        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-01'),
            endDate: CarbonImmutable::parse('2026-07-03'),
        );

        $this->assertSame('full_day', $request->duration_type);
        $this->assertTrue($request->deduct_from_balance);
        $this->assertSame(3.0, (float) $request->days);

        $service->approve($request, $this->admin->id);

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->firstOrFail();

        $this->assertSame(3.0, (float) $entitlement->used_days);
    }

    private function createEmployee(): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-LEAVE-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Layla',
            'last_name' => 'Hassan',
            'email' => 'layla-'.uniqid().'@example.com',
        ]);
    }

    /**
     * @param  callable(): void  $callback
     */
    private function expectExceptionValidationField(LeaveService $service, callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a ValidationException for field [{$field}].");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }
}
