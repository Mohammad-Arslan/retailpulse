<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\HrEmploymentType;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Services\Leave\LeaveEligibilityService;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2LeaveEligibilityTest extends TestCase
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
            'name' => 'Eligibility Branch',
            'code' => 'ELIG',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Eligibility Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'leave'],
        ]);
    }

    public function test_gender_filter_includes_and_excludes(): void
    {
        $leaveType = $this->createLeaveType();
        $policy = $this->createPolicy($leaveType, ['genders' => ['female']]);
        $service = app(LeaveEligibilityService::class);

        $matching = $this->createEmployee(['gender' => 'female']);
        $nonMatching = $this->createEmployee(['gender' => 'male']);

        $this->assertTrue($service->isEligible($matching, $policy, CarbonImmutable::now()));
        $this->assertFalse($service->isEligible($nonMatching, $policy, CarbonImmutable::now()));
    }

    public function test_grade_ids_filter_includes_and_excludes(): void
    {
        $leaveType = $this->createLeaveType();
        $matchingGrade = Grade::query()->create(['code' => 'G-MATCH-'.uniqid(), 'name' => 'Match Grade']);
        $otherGrade = Grade::query()->create(['code' => 'G-OTHER-'.uniqid(), 'name' => 'Other Grade']);
        $policy = $this->createPolicy($leaveType, ['grade_ids' => [$matchingGrade->id]]);
        $service = app(LeaveEligibilityService::class);

        $matching = $this->createEmployee(['grade_id' => $matchingGrade->id]);
        $nonMatching = $this->createEmployee(['grade_id' => $otherGrade->id]);
        $noGrade = $this->createEmployee();

        $this->assertTrue($service->isEligible($matching, $policy, CarbonImmutable::now()));
        $this->assertFalse($service->isEligible($nonMatching, $policy, CarbonImmutable::now()));
        $this->assertFalse($service->isEligible($noGrade, $policy, CarbonImmutable::now()));
    }

    public function test_employment_types_filter_includes_and_excludes(): void
    {
        $leaveType = $this->createLeaveType();
        HrEmploymentType::query()->create(['code' => 'full_time', 'name' => 'Full Time']);
        HrEmploymentType::query()->create(['code' => 'part_time', 'name' => 'Part Time']);
        $policy = $this->createPolicy($leaveType, ['employment_types' => ['full_time']]);
        $service = app(LeaveEligibilityService::class);

        $matching = $this->createEmployee(['employment_type' => 'full_time']);
        $nonMatching = $this->createEmployee(['employment_type' => 'part_time']);

        $this->assertTrue($service->isEligible($matching, $policy, CarbonImmutable::now()));
        $this->assertFalse($service->isEligible($nonMatching, $policy, CarbonImmutable::now()));
    }

    public function test_min_tenure_months_filter_includes_and_excludes(): void
    {
        $leaveType = $this->createLeaveType();
        $policy = $this->createPolicy($leaveType, ['min_tenure_months' => 6]);
        $service = app(LeaveEligibilityService::class);

        $asOf = CarbonImmutable::parse('2026-08-01');
        $longTenure = $this->createEmployee(['hire_date' => '2026-01-01']); // 7 months by asOf
        $shortTenure = $this->createEmployee(['hire_date' => '2026-07-01']); // 1 month by asOf
        $noHireDate = $this->createEmployee(['hire_date' => null]);

        $this->assertTrue($service->isEligible($longTenure, $policy, $asOf));
        $this->assertFalse($service->isEligible($shortTenure, $policy, $asOf));
        $this->assertFalse($service->isEligible($noHireDate, $policy, $asOf));
    }

    public function test_combined_filters_require_every_dimension_to_match(): void
    {
        $leaveType = $this->createLeaveType();
        $grade = Grade::query()->create(['code' => 'G-COMBO-'.uniqid(), 'name' => 'Combo Grade']);
        HrEmploymentType::query()->create(['code' => 'full_time', 'name' => 'Full Time']);
        $policy = $this->createPolicy($leaveType, [
            'genders' => ['female'],
            'grade_ids' => [$grade->id],
            'employment_types' => ['full_time'],
            'min_tenure_months' => 6,
        ]);
        $service = app(LeaveEligibilityService::class);
        $asOf = CarbonImmutable::parse('2026-08-01');

        $fullyMatching = $this->createEmployee([
            'gender' => 'female',
            'grade_id' => $grade->id,
            'employment_type' => 'full_time',
            'hire_date' => '2026-01-01',
        ]);
        $partiallyMatching = $this->createEmployee([
            'gender' => 'female',
            'grade_id' => $grade->id,
            'employment_type' => 'part_time', // fails this one dimension
            'hire_date' => '2026-01-01',
        ]);

        $this->assertTrue($service->isEligible($fullyMatching, $policy, $asOf));
        $this->assertFalse($service->isEligible($partiallyMatching, $policy, $asOf));
    }

    public function test_no_eligibility_json_matches_everyone(): void
    {
        $leaveType = $this->createLeaveType();
        $policy = $this->createPolicy($leaveType, null);
        $service = app(LeaveEligibilityService::class);

        $employee = $this->createEmployee(['gender' => 'male', 'employment_type' => 'part_time']);

        $this->assertTrue($service->isEligible($employee, $policy, CarbonImmutable::now()));
    }

    public function test_request_leave_rejects_an_ineligible_employee(): void
    {
        $leaveType = $this->createLeaveType();
        $this->createPolicy($leaveType, ['genders' => ['female']]);
        $employee = $this->createEmployee(['gender' => 'male']);

        $service = app(LeaveService::class);

        try {
            $service->requestLeave(
                employee: $employee,
                leaveType: $leaveType,
                startDate: CarbonImmutable::parse('2026-08-03'),
                endDate: CarbonImmutable::parse('2026-08-03'),
            );
            $this->fail('Expected a ValidationException for an ineligible employee.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('leave_type_id', $e->errors());
        }

        $this->assertSame(0, LeaveRequest::query()->count());
    }

    public function test_request_leave_succeeds_for_a_policy_with_no_eligibility_filters(): void
    {
        $leaveType = $this->createLeaveType();
        $this->createPolicy($leaveType, null);
        $employee = $this->createEmployee(['gender' => 'male']);

        $service = app(LeaveService::class);

        $request = $service->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse('2026-08-03'),
            endDate: CarbonImmutable::parse('2026-08-03'),
        );

        $this->assertSame('pending', $request->status);
    }

    public function test_process_accrual_skips_an_ineligible_entitlement_and_continues(): void
    {
        $leaveType = $this->createLeaveType();
        $policy = $this->createPolicy($leaveType, ['genders' => ['female']], [
            'accrual_method' => 'monthly_accrual',
            'accrual_rate' => 2,
        ]);

        $eligibleEmployee = $this->createEmployee(['gender' => 'female']);
        $ineligibleEmployee = $this->createEmployee(['gender' => 'male']);

        $eligibleEntitlement = LeaveEntitlement::query()->create([
            'employee_id' => $eligibleEmployee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 0,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
            'accrual_last_run_on' => '2026-06-01',
        ]);
        $ineligibleEntitlement = LeaveEntitlement::query()->create([
            'employee_id' => $ineligibleEmployee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => 0,
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => 0,
            'accrual_last_run_on' => '2026-06-01',
        ]);

        $service = app(LeaveService::class);
        $result = $service->processAccrual(CarbonImmutable::parse('2026-08-01'));

        // Only the eligible entitlement (2 months * 2 accrual_rate = 4 days) should be processed.
        $this->assertSame(1, $result['processed']);
        $this->assertEqualsWithDelta(4.0, $result['total_granted'], 0.001);

        $eligibleEntitlement->refresh();
        $ineligibleEntitlement->refresh();

        $this->assertEqualsWithDelta(4.0, (float) $eligibleEntitlement->accrued_days, 0.001);
        $this->assertSame(0.0, (float) $ineligibleEntitlement->accrued_days, 'Ineligible entitlement must be skipped, not accrued.');
    }

    private function createEmployee(array $overrides = []): Employee
    {
        return Employee::query()->create(array_merge([
            'employee_code' => 'EMP-ELIG-'.uniqid(),
            'legal_entity_id' => $this->entity->id,
            'primary_branch_id' => $this->branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Amina',
            'last_name' => 'Yusuf',
            'gender' => 'female',
            'email' => 'amina-'.uniqid().'@example.com',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLeaveType(array $overrides = []): LeaveType
    {
        return LeaveType::query()->create(array_merge([
            'code' => 'ANNUAL-ELIG-'.uniqid(),
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
