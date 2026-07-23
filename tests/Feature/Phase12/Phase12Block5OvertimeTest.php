<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Models\OvertimeMultiplier;
use App\Models\OvertimePolicy;
use App\Models\User;
use App\Services\Overtime\OvertimeEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Block5OvertimeTest extends TestCase
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
            'name' => 'Overtime Branch',
            'code' => 'OTBR',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->entity = OrganizationEntity::query()->create([
            'legal_name' => 'Overtime Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        BranchHrProfile::query()->create([
            'branch_id' => $this->branch->id,
            'hr_enabled_modules' => ['hr', 'attendance', 'overtime'],
        ]);
    }

    public function test_changing_multiplier_in_db_changes_pay_calculation_without_code_change(): void
    {
        $employee = $this->createEmployee();
        $policy = $this->createPolicyWithMultiplier('1.5000');
        $engine = app(OvertimeEngine::class);
        $date = CarbonImmutable::parse('2026-07-15');

        $workedMinutes = (int) $policy->daily_threshold_minutes + 120;

        $record = $engine->createRecord($employee, $date, $workedMinutes);

        $this->assertSame(120, $record->overtime_minutes);
        $this->assertSame('1.5000', (string) $record->resolved_multiplier);
        $this->assertSame('180.0000', $engine->calculatePayUnits($record));

        OvertimeMultiplier::query()
            ->where('overtime_policy_id', $policy->id)
            ->where('day_type', OvertimeEngine::DAY_TYPE_WEEKDAY)
            ->update(['multiplier' => '2.0000']);

        $recalculated = $engine->createRecord($employee, $date, $workedMinutes);

        $this->assertSame(120, $recalculated->overtime_minutes);
        $this->assertSame('2.0000', (string) $recalculated->resolved_multiplier);
        $this->assertSame('240.0000', $engine->calculatePayUnits($recalculated));
    }

    public function test_unapproved_overtime_excluded_from_approved_records_for_period(): void
    {
        $employee = $this->createEmployee();
        $policy = $this->createPolicyWithMultiplier('1.5000');
        $engine = app(OvertimeEngine::class);
        $date = CarbonImmutable::parse('2026-07-16');
        $workedMinutes = (int) $policy->daily_threshold_minutes + 60;

        $pending = $engine->createRecord($employee, $date, $workedMinutes);
        $approved = $engine->createRecord(
            $employee,
            $date->addDay(),
            $workedMinutes,
        );
        $engine->approveRecord($approved, $this->admin->id);

        $periodStart = $date;
        $periodEnd = $date->addDay();

        $feed = $engine->approvedRecordsForPeriod($employee, $periodStart, $periodEnd);

        $this->assertCount(1, $feed);
        $this->assertSame($approved->id, $feed->first()?->id);
        $this->assertNotContains($pending->id, $feed->pluck('id')->all());
        $this->assertSame('pending', $pending->fresh()?->status);
    }

    public function test_overtime_engine_has_no_hardcoded_threshold_or_multiplier_literals(): void
    {
        $path = app_path('Services/Overtime/OvertimeEngine.php');
        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        foreach (['8', '48', '1.5', '2.0'] as $literal) {
            $this->assertStringNotContainsString(
                $literal,
                $contents,
                "OvertimeEngine.php must not contain hardcoded literal [{$literal}].",
            );
        }
    }

    private function createEmployee(): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-OT-'.uniqid(),
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

    private function createPolicyWithMultiplier(string $multiplier): OvertimePolicy
    {
        $policy = OvertimePolicy::query()->create([
            'legal_entity_id' => $this->entity->id,
            'branch_id' => null,
            'daily_threshold_minutes' => 480,
            'weekly_threshold_minutes' => 2880,
            'rest_day_applies' => false,
            'public_holiday_applies' => false,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => 'active',
            'priority' => 100,
        ]);

        OvertimeMultiplier::query()->create([
            'overtime_policy_id' => $policy->id,
            'day_type' => OvertimeEngine::DAY_TYPE_WEEKDAY,
            'multiplier' => $multiplier,
        ]);

        OvertimeMultiplier::query()->create([
            'overtime_policy_id' => $policy->id,
            'day_type' => OvertimeEngine::DAY_TYPE_WEEKEND,
            'multiplier' => $multiplier,
        ]);

        return $policy;
    }
}
