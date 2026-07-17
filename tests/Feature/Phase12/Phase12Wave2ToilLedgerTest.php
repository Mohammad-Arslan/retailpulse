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
use App\Models\ToilClaim;
use App\Models\ToilLedgerEntry;
use App\Services\Overtime\ToilLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('Phase12')]
final class Phase12Wave2ToilLedgerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private Employee $employee;

    private OvertimePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $branch = Branch::query()->create([
            'name' => 'TOIL Branch',
            'code' => 'TOIL',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $entity = OrganizationEntity::query()->create([
            'legal_name' => 'TOIL Entity',
            'functional_currency_code' => 'USD',
            'status' => 'active',
        ]);

        BranchHrProfile::query()->create([
            'branch_id' => $branch->id,
            'hr_enabled_modules' => ['hr', 'overtime', 'leave'],
        ]);

        $this->employee = Employee::query()->create([
            'employee_code' => 'EMP-TOIL-'.uniqid(),
            'legal_entity_id' => $entity->id,
            'primary_branch_id' => $branch->id,
            'hire_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'first_name' => 'Hina',
            'last_name' => 'Malik',
            'email' => 'hina-'.uniqid().'@example.com',
        ]);

        $this->policy = OvertimePolicy::query()->create([
            'daily_threshold_minutes' => 480,
            'rest_day_applies' => true,
            'public_holiday_applies' => true,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'priority' => 100,
        ]);
    }

    public function test_credit_increases_available_hours(): void
    {
        $service = app(ToilLedgerService::class);
        $record = $this->createOvertimeRecord();

        $service->credit($this->employee, $record, 8.0, CarbonImmutable::parse('2026-12-31'));

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(8.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);

        $entry = ToilLedgerEntry::query()->where('employee_id', $this->employee->id)->where('entry_type', 'credit')->firstOrFail();
        $this->assertSame($record->id, $entry->overtime_record_id);
    }

    public function test_second_hold_that_would_overdraw_the_balance_fails_cleanly(): void
    {
        $service = app(ToilLedgerService::class);
        $record = $this->createOvertimeRecord();
        $service->credit($this->employee, $record, 10.0, null);

        $claimA = ToilClaim::query()->create(['employee_id' => $this->employee->id, 'claim_type' => 'cash', 'hours' => 8]);
        $service->holdForClaim($this->employee, 8.0, $claimA);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(2.0, (float) $balance->available_hours);
        $this->assertSame(8.0, (float) $balance->pending_hours);

        // A second claim for 8 hours can no longer be covered — this is the
        // same balance-check ordering that closes the concurrent double-spend
        // case: whichever hold's transaction commits first reduces the
        // balance the second must check against, so it fails instead of both
        // succeeding.
        $claimB = ToilClaim::query()->create(['employee_id' => $this->employee->id, 'claim_type' => 'cash', 'hours' => 8]);

        try {
            $service->holdForClaim($this->employee, 8.0, $claimB);
            $this->fail('Expected a ValidationException when a hold would exceed the available balance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('hours', $e->errors());
        }

        $balance->refresh();
        $this->assertSame(2.0, (float) $balance->available_hours, 'The failed second hold must not have touched the balance.');
        $this->assertSame(8.0, (float) $balance->pending_hours);
    }

    public function test_debit_only_touches_pending_hours(): void
    {
        $service = app(ToilLedgerService::class);
        $record = $this->createOvertimeRecord();
        $service->credit($this->employee, $record, 10.0, null);

        $claim = ToilClaim::query()->create(['employee_id' => $this->employee->id, 'claim_type' => 'cash', 'hours' => 6]);
        $service->holdForClaim($this->employee, 6.0, $claim);
        $service->debit($claim);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(4.0, (float) $balance->available_hours, 'Debit must not touch available_hours a second time — the hold already removed it.');
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_release_returns_the_hold_to_available(): void
    {
        $service = app(ToilLedgerService::class);
        $record = $this->createOvertimeRecord();
        $service->credit($this->employee, $record, 10.0, null);

        $claim = ToilClaim::query()->create(['employee_id' => $this->employee->id, 'claim_type' => 'cash', 'hours' => 6]);
        $service->holdForClaim($this->employee, 6.0, $claim);
        $service->release($claim);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(10.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_release_after_debit_only_credits_available_without_double_touching_pending(): void
    {
        $service = app(ToilLedgerService::class);
        $record = $this->createOvertimeRecord();
        $service->credit($this->employee, $record, 10.0, null);

        $claim = ToilClaim::query()->create(['employee_id' => $this->employee->id, 'claim_type' => 'cash', 'hours' => 6]);
        $service->holdForClaim($this->employee, 6.0, $claim);
        $service->debit($claim);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(4.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);

        // Cancelling an already-debited (approved) claim must give the hours
        // back to available without ever touching pending_hours a second
        // time — pending was already zeroed out by the debit above.
        $service->release($claim);

        $balance->refresh();
        $this->assertSame(10.0, (float) $balance->available_hours);
        $this->assertSame(0.0, (float) $balance->pending_hours);
    }

    public function test_reconcile_rebuilds_balance_purely_from_the_ledger(): void
    {
        $service = app(ToilLedgerService::class);
        $record = $this->createOvertimeRecord();
        $service->credit($this->employee, $record, 10.0, null);

        $claim = ToilClaim::query()->create(['employee_id' => $this->employee->id, 'claim_type' => 'cash', 'hours' => 4]);
        $service->holdForClaim($this->employee, 4.0, $claim);

        // Deliberately desync the cache to prove reconcile ignores it entirely.
        ToilBalance::query()->where('employee_id', $this->employee->id)->update([
            'available_hours' => 999,
            'pending_hours' => 999,
        ]);

        $reconciled = $service->reconcileBalance($this->employee);

        $this->assertSame(6.0, (float) $reconciled->available_hours);
        $this->assertSame(4.0, (float) $reconciled->pending_hours);
    }

    public function test_expiry_only_consumes_the_unheld_remainder_in_fifo_order(): void
    {
        $service = app(ToilLedgerService::class);

        $recordA = $this->createOvertimeRecord('2026-01-05');
        $creditA = $service->credit($this->employee, $recordA, 10.0, CarbonImmutable::parse('2026-06-01'));

        $recordB = $this->createOvertimeRecord('2026-02-05');
        $service->credit($this->employee, $recordB, 5.0, CarbonImmutable::parse('2026-09-01'));

        // Hold 6 hours — under FIFO this is drawn from credit A (the older, larger credit).
        $claim = ToilClaim::query()->create(['employee_id' => $this->employee->id, 'claim_type' => 'cash', 'hours' => 6]);
        $service->holdForClaim($this->employee, 6.0, $claim);

        // Run expiry as of a date after credit A's expiry but before credit B's.
        $expired = $service->expireDueCredits(CarbonImmutable::parse('2026-07-01'));

        $this->assertCount(1, $expired);
        $this->assertSame(4.0, (float) $expired[0]->hours, 'Only the 4 unconsumed hours from credit A (10 - 6 held) should expire.');
        $this->assertSame($creditA->id, $expired[0]->credit_entry_id);

        $balance = ToilBalance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        // available = 15 credited - 6 held - 4 expired = 5 (all from credit B, not yet due).
        $this->assertSame(5.0, (float) $balance->available_hours);
        $this->assertSame(6.0, (float) $balance->pending_hours);

        // Running expiry again for the same date must not double-expire credit A.
        $expiredAgain = $service->expireDueCredits(CarbonImmutable::parse('2026-07-01'));
        $this->assertCount(0, $expiredAgain);
    }

    private function createOvertimeRecord(string $date = '2026-01-10'): OvertimeRecord
    {
        return OvertimeRecord::query()->create([
            'employee_id' => $this->employee->id,
            'date' => $date,
            'regular_minutes' => 480,
            'overtime_minutes' => 120,
            'day_type' => 'rest_day',
            'resolved_multiplier' => 1.0,
            'overtime_policy_id' => $this->policy->id,
            'status' => 'pending',
        ]);
    }
}
