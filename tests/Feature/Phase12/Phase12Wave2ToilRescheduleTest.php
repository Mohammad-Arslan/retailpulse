<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\LeaveRequestReschedule;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRecord;
use App\Models\ToilBalance;
use App\Models\ToilClaim;
use App\Models\User;
use App\Services\Leave\LeaveService;
use App\Services\Overtime\ToilLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2ToilRescheduleTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Employee $employee;

    private User $admin;

    private LeaveType $toilLeaveType;

    private LeaveType $annualLeaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $branch = Branch::query()->create([
            'name' => 'Toil Reschedule Branch',
            'code' => 'TRB1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $entity = OrganizationEntity::query()->create([
            'legal_name' => 'Toil Reschedule Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $branch->id,
            'hr_enabled_modules' => ['hr', 'overtime', 'leave'],
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->employee = Employee::query()->create([
            'employee_code' => 'EMP-TRB-'.uniqid(),
            'legal_entity_id' => $entity->id,
            'primary_branch_id' => $branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Kamran',
            'last_name' => 'Aziz',
            'email' => 'kamran-'.uniqid().'@example.com',
        ]);

        $this->toilLeaveType = LeaveType::query()->updateOrCreate(
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

        $this->annualLeaveType = LeaveType::query()->create([
            'code' => 'ANNUAL-TRB-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'status' => 'active',
        ]);

        $policy = OvertimePolicy::query()->create([
            'daily_threshold_minutes' => 480,
            'rest_day_applies' => true,
            'public_holiday_applies' => false,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'priority' => 100,
        ]);

        $record = OvertimeRecord::query()->create([
            'employee_id' => $this->employee->id,
            'date' => '2026-06-01',
            'regular_minutes' => 480,
            'overtime_minutes' => 960,
            'day_type' => 'rest_day',
            'resolved_multiplier' => 1.0,
            'overtime_policy_id' => $policy->id,
            'status' => 'pending',
        ]);

        app(ToilLedgerService::class)->credit($this->employee, $record, 16.0, null);
    }

    public function test_reschedule_changes_dates_and_records_an_audit_row_without_touching_the_hold(): void
    {
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $claim = ToilClaim::query()->where('leave_request_id', $request->id)->firstOrFail();
        $this->assertSame(8.0, (float) $claim->hours);

        $rescheduled = $service->reschedule(
            request: $request,
            newStartDate: CarbonImmutable::parse('2026-08-10'),
            newEndDate: CarbonImmutable::parse('2026-08-10'),
            changedByUserId: $this->admin->id,
            reason: 'Client meeting moved',
        );

        $this->assertSame('2026-08-10', $rescheduled->start_date->toDateString());
        $this->assertSame('2026-08-10', $rescheduled->end_date->toDateString());
        $this->assertSame(1.0, (float) $rescheduled->days, 'Days must not change on a pure date reschedule.');

        $audit = LeaveRequestReschedule::query()->where('leave_request_id', $request->id)->firstOrFail();
        $this->assertSame('2026-08-03', $audit->old_start_date->toDateString());
        $this->assertSame('2026-08-10', $audit->new_start_date->toDateString());
        $this->assertSame($this->admin->id, $audit->changed_by);
        $this->assertSame('Client meeting moved', $audit->reason);

        // The hold must be completely untouched by the reschedule.
        $claim->refresh();
        $this->assertSame('pending', $claim->status);
        $this->assertSame(8.0, (float) $claim->hours);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(8.0, (float) $balance->available_hours);
        $this->assertSame(8.0, (float) $balance->pending_hours);
    }

    public function test_reschedule_then_approve_never_double_touches_the_balance(): void
    {
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $service->reschedule($request, CarbonImmutable::parse('2026-08-12'), CarbonImmutable::parse('2026-08-12'), $this->admin->id);
        $approved = $service->approve($request->fresh(), $this->admin->id);

        $this->assertSame('approved', $approved->status);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(8.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours, 'Approval after reschedule must debit exactly once.');
    }

    public function test_reschedule_then_reject_releases_the_hold_correctly(): void
    {
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $service->reschedule($request, CarbonImmutable::parse('2026-08-15'), CarbonImmutable::parse('2026-08-15'), $this->admin->id);
        $service->reject($request->fresh(), $this->admin->id, 'No longer needed');

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(16.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_reschedule_is_rejected_for_non_toil_leave_requests(): void
    {
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->annualLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-05'),
        );

        try {
            $service->reschedule($request, CarbonImmutable::parse('2026-08-10'), CarbonImmutable::parse('2026-08-12'), $this->admin->id);
            $this->fail('Expected a ValidationException rescheduling a non-TOIL leave request.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('leave_type_id', $e->errors());
        }
    }

    public function test_reschedule_is_rejected_once_the_request_is_no_longer_pending(): void
    {
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $approved = $service->approve($request, $this->admin->id);

        try {
            $service->reschedule($approved, CarbonImmutable::parse('2026-08-20'), CarbonImmutable::parse('2026-08-20'), $this->admin->id);
            $this->fail('Expected a ValidationException rescheduling a non-pending request.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }
}
