<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
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
final class Phase12Block4LeaveTest extends TestCase
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
    }

    public function test_approve_updates_used_days(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
        ]);

        LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 10,
            'used_days' => 0,
            'carried_forward_days' => 0,
        ]);

        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-07-01'),
            endDate: CarbonImmutable::parse('2026-07-03'),
        );

        $this->assertSame('pending', $request->status);
        $this->assertSame(3.0, (float) $request->days);

        $service->approve($request, $this->admin->id);

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->firstOrFail();

        $this->assertSame(3.0, (float) $entitlement->used_days);
        $this->assertSame(7.0, (float) $entitlement->remaining_days);

        $request->refresh();
        $this->assertSame('approved', $request->status);
    }

    public function test_unpaid_leave_resolve_payroll_deduction_component_returns_configured_code(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType([
            'code' => 'UNPAID',
            'name' => 'Unpaid Leave',
            'is_paid' => false,
            'affects_payroll' => true,
            'payroll_deduction_component_code' => 'UNPAID_LEAVE',
        ]);

        $service = app(LeaveService::class);
        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-01'),
            endDate: CarbonImmutable::parse('2026-08-02'),
        );

        $approved = $service->approve($request, $this->admin->id);

        $componentCode = $service->resolvePayrollDeductionComponent($employee, $approved);

        $this->assertSame('UNPAID_LEAVE', $componentCode);
        $this->assertNotSame('SALARY_REDUCTION', $componentCode);
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
     * @param  array<string, mixed>  $overrides
     */
    private function createLeaveType(array $overrides = []): LeaveType
    {
        return LeaveType::query()->create(array_merge([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'payroll_deduction_component_code' => null,
            'status' => 'active',
        ], $overrides));
    }
}
