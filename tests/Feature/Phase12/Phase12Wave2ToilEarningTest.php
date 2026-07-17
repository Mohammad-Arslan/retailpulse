<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\EmployeeShiftPreference;
use App\Models\OrganizationEntity;
use App\Models\OvertimePolicy;
use App\Models\ToilBalance;
use App\Models\User;
use App\Services\Overtime\OvertimeEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2ToilEarningTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Employee $employee;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $branch = Branch::query()->create([
            'name' => 'Toil Earning Branch',
            'code' => 'TEB1',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $entity = OrganizationEntity::query()->create([
            'legal_name' => 'Toil Earning Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $branch->id,
            'hr_enabled_modules' => ['hr', 'overtime'],
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->employee = Employee::query()->create([
            'employee_code' => 'EMP-TEB-'.uniqid(),
            'legal_entity_id' => $entity->id,
            'primary_branch_id' => $branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Zara',
            'last_name' => 'Sheikh',
            'email' => 'zara-'.uniqid().'@example.com',
        ]);
    }

    public function test_rest_day_is_detected_from_employee_shift_preference_and_policy_flag(): void
    {
        EmployeeShiftPreference::query()->create([
            'employee_id' => $this->employee->id,
            'rest_days' => [5], // Friday
        ]);

        $policy = OvertimePolicy::query()->create([
            'daily_threshold_minutes' => 480,
            'rest_day_applies' => true,
            'public_holiday_applies' => false,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'priority' => 100,
        ]);
        $policy->multipliers()->create(['day_type' => 'rest_day', 'multiplier' => 1.0, 'compensation_type' => 'cash']);
        $policy->multipliers()->create(['day_type' => 'weekday', 'multiplier' => 1.5, 'compensation_type' => 'cash']);

        $engine = app(OvertimeEngine::class);

        // 2026-07-24 is a Friday.
        $record = $engine->createRecord($this->employee, CarbonImmutable::parse('2026-07-24'), 600);
        $this->assertSame('rest_day', $record->day_type);

        // A policy with rest_day_applies=false must fall back to calendar weekday/weekend.
        $policy->update(['rest_day_applies' => false]);
        $record2 = $engine->createRecord($this->employee, CarbonImmutable::parse('2026-07-27'), 600);
        $this->assertSame('weekday', $record2->day_type); // 2026-07-27 is a Monday
    }

    public function test_approving_a_toil_multiplier_credits_the_ledger_using_resolved_multiplier(): void
    {
        $policy = OvertimePolicy::query()->create([
            'daily_threshold_minutes' => 480,
            'rest_day_applies' => true,
            'public_holiday_applies' => false,
            'toil_expiry_months' => 6,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'priority' => 100,
        ]);
        $policy->multipliers()->create(['day_type' => 'rest_day', 'multiplier' => 1.5, 'compensation_type' => 'toil']);
        $policy->multipliers()->create(['day_type' => 'weekday', 'multiplier' => 1.5, 'compensation_type' => 'cash']);

        $engine = app(OvertimeEngine::class);
        $record = $engine->createRecord($this->employee, CarbonImmutable::parse('2026-07-25'), 600, null, 'rest_day');
        $this->assertSame(120, $record->overtime_minutes);

        $approved = $engine->approveRecord($record, $this->admin->id);

        $this->assertSame('toil', $approved->compensation_choice);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        // 120 overtime minutes = 2 hours * 1.5 multiplier = 3 hours.
        $this->assertSame(3.0, (float) $balance->available_hours);

        $entry = $this->employee->toilLedgerEntries()->where('entry_type', 'credit')->firstOrFail();
        $this->assertNotNull($entry->expires_at);
        $this->assertTrue($entry->expires_at->isSameDay(CarbonImmutable::parse('2027-01-25')));
    }

    public function test_cash_multiplier_never_credits_toil(): void
    {
        $policy = OvertimePolicy::query()->create([
            'daily_threshold_minutes' => 480,
            'rest_day_applies' => false,
            'public_holiday_applies' => false,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'priority' => 100,
        ]);
        $policy->multipliers()->create(['day_type' => 'weekday', 'multiplier' => 1.5, 'compensation_type' => 'cash']);

        $engine = app(OvertimeEngine::class);
        $record = $engine->createRecord($this->employee, CarbonImmutable::parse('2026-07-27'), 600);
        $approved = $engine->approveRecord($record, $this->admin->id);

        $this->assertSame('cash', $approved->compensation_choice);
        $this->assertNull(ToilBalance::query()->where('employee_id', $this->employee->id)->first());
    }

    public function test_employee_choice_requires_a_valid_choice_at_approval_time(): void
    {
        $policy = OvertimePolicy::query()->create([
            'daily_threshold_minutes' => 480,
            'rest_day_applies' => false,
            'public_holiday_applies' => true,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'priority' => 100,
        ]);
        $policy->multipliers()->create(['day_type' => 'public_holiday', 'multiplier' => 2.0, 'compensation_type' => 'employee_choice']);

        $engine = app(OvertimeEngine::class);
        $record = $engine->createRecord($this->employee, CarbonImmutable::parse('2026-07-27'), 600, null, 'public_holiday');

        try {
            $engine->approveRecord($record, $this->admin->id, null);
            $this->fail('Expected a ValidationException when no compensation_choice is supplied for an employee_choice multiplier.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('compensation_choice', $e->errors());
        }

        $record->refresh();
        $this->assertSame('pending', $record->status, 'A missing choice must not leave the record partially approved.');

        $approved = $engine->approveRecord($record, $this->admin->id, 'toil');
        $this->assertSame('toil', $approved->compensation_choice);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        // 120 overtime minutes = 2 hours * 2.0 multiplier = 4 hours.
        $this->assertSame(4.0, (float) $balance->available_hours);
    }

    public function test_compensation_choice_is_never_re_evaluated_after_policy_changes(): void
    {
        $policy = OvertimePolicy::query()->create([
            'daily_threshold_minutes' => 480,
            'rest_day_applies' => false,
            'public_holiday_applies' => false,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'priority' => 100,
        ]);
        $policy->multipliers()->create(['day_type' => 'weekday', 'multiplier' => 1.5, 'compensation_type' => 'toil']);

        $engine = app(OvertimeEngine::class);
        $record = $engine->createRecord($this->employee, CarbonImmutable::parse('2026-07-27'), 600);
        $approved = $engine->approveRecord($record, $this->admin->id);
        $this->assertSame('toil', $approved->compensation_choice);

        // Now flip the policy default to cash — the already-approved record's choice must not change.
        $policy->multipliers()->where('day_type', 'weekday')->update(['compensation_type' => 'cash']);

        $approved->refresh();
        $this->assertSame('toil', $approved->compensation_choice);
    }
}
