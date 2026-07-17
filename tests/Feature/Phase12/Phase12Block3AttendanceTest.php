<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSource;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Models\User;
use App\Services\Attendance\AttendanceClockPayload;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\Contracts\AttendanceSourceProvider;
use App\Services\Hr\BranchHrPayrollModuleGate;
use App\Services\Hr\Contracts\HrPayrollModuleGate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Block3AttendanceTest extends TestCase
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
            'name' => 'Attendance Branch',
            'code' => 'ATTN',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Attendance Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');
    }

    private function actingAsBranchAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['branch_id' => $this->branch->id]);
    }

    public function test_registering_new_driver_only_needs_interface_and_config_row(): void
    {
        $this->enableAttendanceModules();

        $service = app(AttendanceService::class);
        $service->registerProvider('fake', new FakeAttendanceProvider);

        $source = AttendanceSource::query()->create([
            'driver' => 'fake',
            'name' => 'Fake Driver',
            'status' => 'active',
            'config_json' => ['mode' => 'test'],
            'branch_id' => null,
        ]);

        $employee = $this->createEmployee();

        $record = $service->clockIn($source, new AttendanceClockPayload(
            employeeId: $employee->id,
            branchId: $this->branch->id,
            at: CarbonImmutable::parse('2026-07-15 09:00:00'),
        ));

        $this->assertSame('open', $record->status);
        $this->assertSame($employee->id, $record->employee_id);
        $this->assertSame($source->id, $record->source_id);
        $this->assertSame('fake', $record->source->driver);
    }

    public function test_worked_minutes_are_derived_when_record_is_closed(): void
    {
        $this->enableAttendanceModules();
        $this->seedManualSource();

        $service = app(AttendanceService::class);
        $employee = $this->createEmployee();
        $source = AttendanceSource::query()->where('driver', 'manual')->firstOrFail();

        $openRecord = $service->clockIn($source, new AttendanceClockPayload(
            employeeId: $employee->id,
            branchId: $this->branch->id,
            at: CarbonImmutable::parse('2026-07-15 09:00:00'),
        ));

        $closedRecord = $service->clockOut($source, new AttendanceClockPayload(
            employeeId: $employee->id,
            branchId: $this->branch->id,
            at: CarbonImmutable::parse('2026-07-15 17:00:00'),
            openRecordId: $openRecord->id,
        ));

        $this->assertSame('closed', $closedRecord->status);
        $this->assertSame(480, $closedRecord->worked_minutes);
        $this->assertSame('2026-07-15 17:00:00', $closedRecord->clock_out?->toDateTimeString());

        $persisted = AttendanceRecord::query()->findOrFail($closedRecord->id);
        $this->assertSame(480, $persisted->worked_minutes);
    }

    public function test_attendance_module_gate_requires_hr(): void
    {
        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['attendance'],
        ]);

        $this->assertFalse((new BranchHrPayrollModuleGate)->isEnabled('attendance', $this->branch->id));

        BranchHrProfile::query()->where('branch_id', $this->branch->id)->update([
            'hr_enabled_modules' => ['hr', 'attendance'],
        ]);

        $this->assertTrue((new BranchHrPayrollModuleGate)->isEnabled('attendance', $this->branch->id));

        BranchHrProfile::query()->where('branch_id', $this->branch->id)->update([
            'hr_enabled_modules' => ['attendance'],
        ]);

        $this->refreshHrModuleGate();

        $this->actingAsBranchAdmin()
            ->get(route('admin.attendance.records.index'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');

        BranchHrProfile::query()->where('branch_id', $this->branch->id)->update([
            'hr_enabled_modules' => ['hr', 'attendance'],
        ]);

        $this->refreshHrModuleGate();

        $this->actingAsBranchAdmin()
            ->get(route('admin.attendance.records.index'))
            ->assertOk();
    }

    private function refreshHrModuleGate(): void
    {
        $this->app->forgetInstance(HrPayrollModuleGate::class);
        $this->app->singleton(HrPayrollModuleGate::class, BranchHrPayrollModuleGate::class);
    }

    private function enableAttendanceModules(): void
    {
        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'attendance'],
        ]);
    }

    private function seedManualSource(): void
    {
        AttendanceSource::query()->firstOrCreate(
            [
                'driver' => 'manual',
                'branch_id' => null,
            ],
            [
                'name' => 'Manual Entry',
                'config_json' => [],
                'status' => 'active',
            ],
        );
    }

    private function createEmployee(): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-ATT-001',
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'email' => 'sara@example.com',
        ]);
    }
}

final class FakeAttendanceProvider implements AttendanceSourceProvider
{
    public function driverKey(): string
    {
        return 'fake';
    }

    public function clockIn(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        return AttendanceRecord::query()->create([
            'employee_id' => $payload->employeeId,
            'branch_id' => $payload->branchId,
            'source_id' => $source->id,
            'clock_in' => $payload->at ?? CarbonImmutable::now(),
            'status' => 'open',
        ]);
    }

    public function clockOut(AttendanceSource $source, AttendanceClockPayload $payload): AttendanceRecord
    {
        $record = AttendanceRecord::query()->findOrFail($payload->openRecordId);

        $at = $payload->at ?? CarbonImmutable::now();
        $record->update([
            'clock_out' => $at,
            'worked_minutes' => (int) $record->clock_in->diffInMinutes($at),
            'status' => 'closed',
        ]);

        return $record->fresh() ?? $record;
    }
}
