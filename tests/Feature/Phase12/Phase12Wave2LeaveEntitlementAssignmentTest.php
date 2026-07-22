<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\DTOs\Hr\CreateEmployeeData;
use App\DTOs\Hr\UpdateEmployeeData;
use App\Jobs\Leave\ReevaluateLeaveEligibilityForPolicyJob;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\HrEmploymentType;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Models\User;
use App\Services\Hr\EmployeeService;
use App\Services\Leave\LeaveEntitlementAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveEntitlementAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Branch $branch;

    private OrganizationEntity $entity;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->branch = Branch::query()->create([
            'name' => 'Assignment Branch',
            'code' => 'ASGN',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Assignment Entity',
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

    public function test_employee_creation_creates_entitlements_only_for_eligible_pairs(): void
    {
        $eligibleType = $this->createLeaveType(['code' => 'ANNUAL-ASGN-'.uniqid()]);
        $this->createPolicy($eligibleType, null);

        $ineligibleType = $this->createLeaveType(['code' => 'RESTRICTED-ASGN-'.uniqid()]);
        $this->createPolicy($ineligibleType, ['genders' => ['female']]);

        $data = new CreateEmployeeData(
            employee: [
                'first_name' => 'Hamza',
                'last_name' => 'Riaz',
                'gender' => 'male',
                'legal_entity_id' => $this->entity->id,
                'primary_branch_id' => $this->branch->id,
                'hire_date' => '2026-03-01',
                'employment_type' => 'full_time',
                'status' => 'active',
            ],
            profile: null,
            shift: null,
            medical: null,
            dependents: [],
            bankAccounts: [],
            branchAssignments: [],
            holidayCalendarId: null,
            imageUploads: [],
        );

        $employee = app(EmployeeService::class)->create($data);

        $leaveTypeIds = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->pluck('leave_type_id')
            ->all();

        $this->assertSame([$eligibleType->id], $leaveTypeIds);
    }

    public function test_grade_change_adds_a_newly_eligible_entitlement_without_touching_existing_ones(): void
    {
        $alwaysEligibleType = $this->createLeaveType(['code' => 'ANNUAL-ASGN-'.uniqid()]);
        $this->createPolicy($alwaysEligibleType, null, ['accrual_method' => 'fixed_annual', 'accrual_rate' => 10]);

        $targetGrade = Grade::query()->create(['code' => 'G-ASGN-'.uniqid(), 'name' => 'Target Grade']);
        $otherGrade = Grade::query()->create(['code' => 'G-ASGN-OTHER-'.uniqid(), 'name' => 'Other Grade']);
        $gradeGatedType = $this->createLeaveType(['code' => 'GRADE-GATED-'.uniqid()]);
        $this->createPolicy($gradeGatedType, ['grade_ids' => [$targetGrade->id]]);

        $employee = app(EmployeeService::class)->create(new CreateEmployeeData(
            employee: [
                'first_name' => 'Sara',
                'last_name' => 'Malik',
                'gender' => 'female',
                'legal_entity_id' => $this->entity->id,
                'primary_branch_id' => $this->branch->id,
                'grade_id' => $otherGrade->id,
                'hire_date' => '2026-03-01',
                'employment_type' => 'full_time',
                'status' => 'active',
            ],
            profile: null,
            shift: null,
            medical: null,
            dependents: [],
            bankAccounts: [],
            branchAssignments: [],
            holidayCalendarId: null,
            imageUploads: [],
        ));

        $entitlementBefore = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $alwaysEligibleType->id)
            ->firstOrFail();
        $this->assertSame(10.0, (float) $entitlementBefore->accrued_days);
        $this->assertSame(
            0,
            LeaveEntitlement::query()->where('employee_id', $employee->id)->where('leave_type_id', $gradeGatedType->id)->count(),
        );

        app(EmployeeService::class)->update($employee, new UpdateEmployeeData(
            employee: ['grade_id' => $targetGrade->id],
            profile: null,
            shift: null,
            medical: null,
            dependents: [],
            bankAccounts: [],
            branchAssignments: [],
            holidayCalendarId: null,
            holidayCalendarProvided: false,
            imageUploads: [],
            removeImageIds: [],
        ));

        $this->assertSame(
            1,
            LeaveEntitlement::query()->where('employee_id', $employee->id)->where('leave_type_id', $gradeGatedType->id)->count(),
            'Grade change must trigger evaluation and create the newly-eligible entitlement.',
        );

        $entitlementBefore->refresh();
        $this->assertSame(10.0, (float) $entitlementBefore->accrued_days, 'The pre-existing entitlement must be untouched by the grade change.');
        $this->assertSame(
            1,
            LeaveEntitlement::query()->where('employee_id', $employee->id)->where('leave_type_id', $alwaysEligibleType->id)->count(),
            'The pre-existing entitlement must not be duplicated.',
        );
    }

    public function test_employment_type_change_adds_a_newly_eligible_entitlement(): void
    {
        HrEmploymentType::query()->create(['code' => 'contract', 'name' => 'Contract']);
        $contractGatedType = $this->createLeaveType(['code' => 'CONTRACT-GATED-'.uniqid()]);
        $this->createPolicy($contractGatedType, ['employment_types' => ['contract']]);

        $employee = app(EmployeeService::class)->create(new CreateEmployeeData(
            employee: [
                'first_name' => 'Bilal',
                'last_name' => 'Ahmed',
                'gender' => 'male',
                'legal_entity_id' => $this->entity->id,
                'primary_branch_id' => $this->branch->id,
                'hire_date' => '2026-03-01',
                'employment_type' => 'full_time',
                'status' => 'active',
            ],
            profile: null,
            shift: null,
            medical: null,
            dependents: [],
            bankAccounts: [],
            branchAssignments: [],
            holidayCalendarId: null,
            imageUploads: [],
        ));

        $this->assertSame(
            0,
            LeaveEntitlement::query()->where('employee_id', $employee->id)->where('leave_type_id', $contractGatedType->id)->count(),
        );

        app(EmployeeService::class)->update($employee, new UpdateEmployeeData(
            employee: ['employment_type' => 'contract'],
            profile: null,
            shift: null,
            medical: null,
            dependents: [],
            bankAccounts: [],
            branchAssignments: [],
            holidayCalendarId: null,
            holidayCalendarProvided: false,
            imageUploads: [],
            removeImageIds: [],
        ));

        $this->assertSame(
            1,
            LeaveEntitlement::query()->where('employee_id', $employee->id)->where('leave_type_id', $contractGatedType->id)->count(),
            'Employment type change must trigger evaluation and create the newly-eligible entitlement.',
        );
    }

    public function test_tightening_policy_eligibility_never_touches_existing_entitlements(): void
    {
        $leaveType = $this->createLeaveType();
        $policy = $this->createPolicy($leaveType, null);

        $employee = $this->createEmployee(['gender' => 'male']);
        $entitlement = app(LeaveEntitlementAssignmentService::class)->evaluateForEmployee($employee)[0];
        $this->assertSame(0.0, (float) $entitlement->accrued_days);

        // Tighten the policy so this employee would no longer be eligible.
        $policy->update(['eligibility_json' => ['genders' => ['female']]]);

        // Simulate the policy-change re-check running (normally queued).
        app(LeaveEntitlementAssignmentService::class)->evaluateForEmployee($employee);

        $this->assertSame(1, LeaveEntitlement::query()->where('employee_id', $employee->id)->count(), 'Must not be deleted.');

        $entitlement->refresh();
        $this->assertSame(0.0, (float) $entitlement->accrued_days, 'Must not be zeroed or otherwise altered.');
    }

    public function test_manual_grant_bypasses_eligibility_and_is_never_touched_by_automatic_evaluation(): void
    {
        $leaveType = $this->createLeaveType();
        $this->createPolicy($leaveType, ['genders' => ['female']]);
        $employee = $this->createEmployee(['gender' => 'male']);

        $response = $this->actingAs($this->admin)->post(route('admin.leave.entitlements.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'accrued_days' => 5,
            'carried_forward_days' => 0,
        ]);

        $response->assertSessionHasNoErrors();

        $entitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->firstOrFail();

        $this->assertTrue($entitlement->granted_manually);
        $this->assertSame(5.0, (float) $entitlement->accrued_days);

        app(LeaveEntitlementAssignmentService::class)->evaluateForEmployee($employee);

        $this->assertSame(
            1,
            LeaveEntitlement::query()->where('employee_id', $employee->id)->where('leave_type_id', $leaveType->id)->count(),
            'Automatic evaluation must never duplicate a manually granted entitlement.',
        );

        $entitlement->refresh();
        $this->assertSame(5.0, (float) $entitlement->accrued_days, 'Automatic evaluation must never alter a manually granted entitlement.');
    }

    public function test_leave_policy_store_dispatches_the_reevaluation_job(): void
    {
        Bus::fake();

        $leaveType = $this->createLeaveType();

        $this->actingAs($this->admin)->post(route('admin.leave.policies.store'), [
            'leave_type_id' => $leaveType->id,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 5,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        Bus::assertDispatched(ReevaluateLeaveEligibilityForPolicyJob::class);
    }

    public function test_leave_policy_update_does_not_dispatch_the_job_when_eligibility_fields_are_unchanged(): void
    {
        $leaveType = $this->createLeaveType();
        $policy = $this->createPolicy($leaveType, null);

        Bus::fake();

        $this->actingAs($this->admin)->put(route('admin.leave.policies.update', $policy), [
            'accrual_rate' => 7,
        ])->assertSessionHasNoErrors();

        Bus::assertNotDispatched(ReevaluateLeaveEligibilityForPolicyJob::class);
    }

    public function test_reevaluation_job_creates_entitlements_for_newly_eligible_employees_in_scope(): void
    {
        $leaveType = $this->createLeaveType();
        $policy = $this->createPolicy($leaveType, ['genders' => ['female']]);

        $eligibleEmployee = $this->createEmployee(['gender' => 'female']);
        $ineligibleEmployee = $this->createEmployee(['gender' => 'male']);

        $job = new ReevaluateLeaveEligibilityForPolicyJob($policy);
        $job->handle(app(LeaveEntitlementAssignmentService::class));

        $this->assertSame(
            1,
            LeaveEntitlement::query()->where('employee_id', $eligibleEmployee->id)->where('leave_type_id', $leaveType->id)->count(),
        );
        $this->assertSame(
            0,
            LeaveEntitlement::query()->where('employee_id', $ineligibleEmployee->id)->where('leave_type_id', $leaveType->id)->count(),
        );
    }

    private function createEmployee(array $overrides = []): Employee
    {
        return Employee::query()->create(array_merge([
            'employee_code' => 'EMP-ASGN-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Zara',
            'last_name' => 'Khan',
            'gender' => 'female',
            'email' => 'zara-'.uniqid().'@example.com',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLeaveType(array $overrides = []): LeaveType
    {
        return LeaveType::query()->create(array_merge([
            'code' => 'ANNUAL-ASGN-'.uniqid(),
            'name' => 'Annual Leave',
            'is_paid' => true,
            'affects_payroll' => false,
            'status' => 'active',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>|null  $eligibilityJson
     * @param  array<string, mixed>  $overrides
     */
    private function createPolicy(LeaveType $leaveType, ?array $eligibilityJson, array $overrides = []): LeavePolicy
    {
        return LeavePolicy::query()->create(array_merge([
            'leave_type_id' => $leaveType->id,
            'legal_entity_id' => null,
            'accrual_method' => 'fixed_annual',
            'accrual_rate' => 0,
            'eligibility_json' => $eligibilityJson,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ], $overrides));
    }
}
