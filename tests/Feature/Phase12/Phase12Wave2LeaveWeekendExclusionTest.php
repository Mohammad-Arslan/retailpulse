<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\HrEntitySetting;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Models\User;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveWeekendExclusionTest extends TestCase
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
            'name' => 'Weekend Branch',
            'code' => 'WKND',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Weekend Entity',
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
            'code' => 'ANNUAL-WKND-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'status' => 'active',
        ]);
    }

    public function test_default_weekend_saturday_sunday_is_excluded(): void
    {
        $this->createPolicy(['exclude_weekends' => true]);
        $employee = $this->createEmployee();

        // 2026-07-20 is a Monday; 2026-07-26 is the following Sunday — one full Sat/Sun weekend inside the range.
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-20'),
            endDate: CarbonImmutable::parse('2026-07-26'),
        );

        // 7 calendar days minus Saturday (25th) and Sunday (26th) = 5 days.
        $this->assertSame(5.0, (float) $request->days);
    }

    public function test_custom_weekend_days_from_hr_entity_settings_are_respected(): void
    {
        HrEntitySetting::query()->create([
            'legal_entity_id' => $this->entity->id,
            'settings_json' => ['weekend_days' => [5, 6]], // Friday + Saturday
        ]);

        $this->createPolicy(['exclude_weekends' => true]);
        $employee = $this->createEmployee();

        // 2026-07-20 Mon → 2026-07-26 Sun: Friday 24th + Saturday 25th are the configured weekend.
        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-20'),
            endDate: CarbonImmutable::parse('2026-07-26'),
        );

        $this->assertSame(5.0, (float) $request->days);
    }

    public function test_exclude_weekends_disabled_counts_every_calendar_day(): void
    {
        $this->createPolicy(['exclude_weekends' => false]);
        $employee = $this->createEmployee();

        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $this->leaveType,
            startDate: CarbonImmutable::parse('2026-07-20'),
            endDate: CarbonImmutable::parse('2026-07-26'),
        );

        $this->assertSame(7.0, (float) $request->days);
    }

    private function createPolicy(array $overrides = []): LeavePolicy
    {
        return LeavePolicy::query()->create(array_merge([
            'leave_type_id' => $this->leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'exclude_public_holidays' => false,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ], $overrides));
    }

    private function createEmployee(): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-WKND-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Omar',
            'last_name' => 'Farooq',
            'email' => 'omar-'.uniqid().'@example.com',
        ]);
    }
}
