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
use App\Models\ToilBalance;
use App\Models\ToilClaim;
use App\Models\User;
use App\Services\Leave\LeaveEncashmentService;
use App\Services\Leave\LeaveService;
use App\Services\Overtime\ToilLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2ToilLeaveClaimTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Employee $employee;

    private User $admin;

    private LeaveType $toilLeaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $branch = Branch::query()->create([
            'name' => 'Toil Claim Branch',
            'code' => 'TCB1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $entity = OrganizationEntity::query()->create([
            'legal_name' => 'Toil Claim Entity',
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
            'employee_code' => 'EMP-TCB-'.uniqid(),
            'legal_entity_id' => $entity->id,
            'primary_branch_id' => $branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Adeel',
            'last_name' => 'Rana',
            'email' => 'adeel-'.uniqid().'@example.com',
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

        $this->creditToil(16.0);
    }

    public function test_full_day_toil_claim_holds_the_converted_hours(): void
    {
        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $this->assertSame('pending', $request->status);
        $this->assertSame(1.0, (float) $request->days);

        $claim = ToilClaim::query()->where('leave_request_id', $request->id)->firstOrFail();
        $this->assertSame('leave', $claim->claim_type);
        $this->assertSame('pending', $claim->status);
        // 1 day * 8 default work hours per day = 8 hours held.
        $this->assertSame(8.0, (float) $claim->hours);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(8.0, (float) $balance->available_hours);
        $this->assertSame(8.0, (float) $balance->pending_hours);
    }

    public function test_approving_a_toil_leave_claim_converts_the_hold_to_a_debit(): void
    {
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $approved = $service->approve($request, $this->admin->id);
        $this->assertSame('approved', $approved->status);

        $claim = ToilClaim::query()->where('leave_request_id', $request->id)->firstOrFail();
        $this->assertSame('approved', $claim->status);
        $this->assertNotNull($claim->approved_at);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(8.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_rejecting_a_toil_leave_claim_releases_the_hold(): void
    {
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $service->reject($request, $this->admin->id, 'Not eligible');

        $claim = ToilClaim::query()->where('leave_request_id', $request->id)->firstOrFail();
        $this->assertSame('rejected', $claim->status);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(16.0, (float) $balance->available_hours, 'Rejection must return the held hours to available.');
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_cancelling_an_approved_toil_leave_claim_releases_correctly(): void
    {
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );
        $approved = $service->approve($request, $this->admin->id);

        $service->cancel($approved, $this->admin->id);

        $claim = ToilClaim::query()->where('leave_request_id', $request->id)->firstOrFail();
        $this->assertSame('cancelled', $claim->status);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(16.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_claim_exceeding_available_balance_is_rejected_and_creates_no_rows(): void
    {
        $service = app(LeaveService::class);

        try {
            $service->requestLeave(
                employee: $this->employee,
                leaveType: $this->toilLeaveType,
                startDate: CarbonImmutable::parse('2026-08-03'),
                endDate: CarbonImmutable::parse('2026-08-05'), // 3 days = 24 hours > 16 available
            );
            $this->fail('Expected a ValidationException when the TOIL claim exceeds the available balance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('hours', $e->errors());
        }

        $this->assertSame(0, LeaveRequest::query()->count(), 'A failed hold must roll back the LeaveRequest creation too.');
        $this->assertSame(0, ToilClaim::query()->count());

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(16.0, (float) $balance->available_hours, 'A failed claim must never touch the balance.');
    }

    public function test_short_leave_toil_claim_converts_hours_directly_without_double_conversion(): void
    {
        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $this->employee,
            leaveType: $this->toilLeaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
            durationType: 'short_leave',
            startTime: '09:00',
            endTime: '12:00',
        );

        // 3 hours / 8 work-hours-per-day = 0.375 days -> converted back: 0.375 * 8 = 3 hours held.
        $claim = ToilClaim::query()->where('leave_request_id', $request->id)->firstOrFail();
        $this->assertSame(3.0, (float) $claim->hours);
    }

    public function test_leave_claim_is_rejected_when_the_leave_type_disallows_it(): void
    {
        $this->toilLeaveType->update(['allow_leave_claim' => false]);

        $service = app(LeaveService::class);

        try {
            $service->requestLeave(
                employee: $this->employee,
                leaveType: $this->toilLeaveType,
                startDate: CarbonImmutable::parse('2026-08-03'),
                endDate: CarbonImmutable::parse('2026-08-03'),
            );
            $this->fail('Expected a ValidationException when allow_leave_claim is false.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('leave_type_id', $e->errors());
        }

        $this->assertSame(0, LeaveRequest::query()->count());
    }

    public function test_generic_leave_encashment_rejects_the_toil_leave_type(): void
    {
        $service = app(LeaveEncashmentService::class);

        try {
            $service->requestEncashment($this->employee, $this->toilLeaveType, 2.0);
            $this->fail('Expected a ValidationException rejecting TOIL from the generic encashment flow.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('leave_type_id', $e->errors());
        }
    }

    private function creditToil(float $hours): void
    {
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
            'date' => '2026-07-11',
            'regular_minutes' => 480,
            'overtime_minutes' => (int) ($hours * 60),
            'day_type' => 'rest_day',
            'resolved_multiplier' => 1.0,
            'overtime_policy_id' => $policy->id,
            'status' => 'pending',
        ]);

        app(ToilLedgerService::class)->credit($this->employee, $record, $hours, null);
    }
}
