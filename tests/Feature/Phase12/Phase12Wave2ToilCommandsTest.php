<?php

declare(strict_types=1);

namespace Tests\Feature\Phase12;

use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRecord;
use App\Models\ToilBalance;
use App\Services\Overtime\ToilLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2ToilCommandsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $branch = Branch::query()->create([
            'name' => 'Toil Commands Branch',
            'code' => 'TCMD',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $entity = OrganizationEntity::query()->create([
            'legal_name' => 'Toil Commands Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $branch->id,
            'hr_enabled_modules' => ['hr', 'overtime'],
        ]);

        $this->employee = Employee::query()->create([
            'employee_code' => 'EMP-TCMD-'.uniqid(),
            'legal_entity_id' => $entity->id,
            'primary_branch_id' => $branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Rida',
            'last_name' => 'Baig',
            'email' => 'rida-'.uniqid().'@example.com',
        ]);
    }

    public function test_expire_credits_command_expires_a_past_due_credit(): void
    {
        $this->creditToil(8.0, '2026-01-01');

        Artisan::call('toil:expire-credits', ['--as-of' => '2026-07-01']);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(0.0, (float) $balance->available_hours);
    }

    public function test_expire_credits_command_is_a_no_op_when_nothing_is_due(): void
    {
        $this->creditToil(8.0, '2027-01-01');

        Artisan::call('toil:expire-credits', ['--as-of' => '2026-07-01']);

        $output = Artisan::output();
        $this->assertStringContainsString('No TOIL credits due', $output);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(8.0, (float) $balance->available_hours);
    }

    public function test_reconcile_balances_command_fixes_a_drifted_cache(): void
    {
        $this->creditToil(5.0, null);

        ToilBalance::query()->where('employee_id', $this->employee->id)->update(['available_hours' => 999]);

        Artisan::call('toil:reconcile-balances');

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(5.0, (float) $balance->available_hours);
    }

    private function creditToil(float $hours, ?string $expiresAt): void
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
            'date' => '2025-12-01',
            'regular_minutes' => 480,
            'overtime_minutes' => (int) ($hours * 60),
            'day_type' => 'rest_day',
            'resolved_multiplier' => 1.0,
            'overtime_policy_id' => $policy->id,
            'status' => 'pending',
        ]);

        app(ToilLedgerService::class)->credit(
            $this->employee,
            $record,
            $hours,
            $expiresAt !== null ? CarbonImmutable::parse($expiresAt) : null,
        );
    }
}
