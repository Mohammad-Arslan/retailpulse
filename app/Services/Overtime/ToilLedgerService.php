<?php

declare(strict_types=1);

namespace App\Services\Overtime;

use App\Models\Employee;
use App\Models\OvertimeRecord;
use App\Models\ToilBalance;
use App\Models\ToilClaim;
use App\Models\ToilLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Append-only ledger for TOIL (Time Off In Lieu) hours.
 *
 * The ledger is the source of truth. `toil_balances` is a derived
 * read-optimization cache that is only ever mutated in the same transaction
 * as the ledger entry that changed it, and can be fully rebuilt from the
 * ledger via reconcileBalance().
 */
final class ToilLedgerService
{
    public function credit(
        Employee $employee,
        OvertimeRecord $record,
        float $hours,
        ?CarbonImmutable $expiresAt,
        ?int $createdBy = null,
    ): ToilLedgerEntry {
        if ($hours <= 0) {
            throw ValidationException::withMessages([
                'hours' => __('TOIL credit hours must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($employee, $record, $hours, $expiresAt, $createdBy): ToilLedgerEntry {
            $balance = $this->lockBalance($employee);

            $entry = ToilLedgerEntry::query()->create([
                'employee_id' => $employee->id,
                'entry_type' => 'credit',
                'hours' => $hours,
                'earned_date' => $record->date,
                'expires_at' => $expiresAt,
                'overtime_record_id' => $record->id,
                'created_by' => $createdBy,
            ]);

            $balance->increment('available_hours', $hours);

            return $entry;
        });
    }

    /**
     * The single choke point both claim types go through. Locking the balance
     * row here serializes concurrent claims for the same employee, so a
     * second claim that would overdraw the balance always fails cleanly
     * instead of racing the first.
     */
    public function holdForClaim(Employee $employee, float $hours, ToilClaim $claim): ToilLedgerEntry
    {
        if ($hours <= 0) {
            throw ValidationException::withMessages([
                'hours' => __('TOIL claim hours must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($employee, $hours, $claim): ToilLedgerEntry {
            $balance = $this->lockBalance($employee);

            if ($hours > (float) $balance->available_hours) {
                throw ValidationException::withMessages([
                    'hours' => __('This claim of :hours hours exceeds the available TOIL balance of :available hours.', [
                        'hours' => (string) $hours,
                        'available' => (string) $balance->available_hours,
                    ]),
                ]);
            }

            $entry = ToilLedgerEntry::query()->create([
                'employee_id' => $employee->id,
                'entry_type' => 'hold',
                'hours' => $hours,
                'toil_claim_id' => $claim->id,
            ]);

            $balance->decrement('available_hours', $hours);
            $balance->increment('pending_hours', $hours);

            return $entry;
        });
    }

    public function debit(ToilClaim $claim): ToilLedgerEntry
    {
        return DB::transaction(function () use ($claim): ToilLedgerEntry {
            $employee = Employee::query()->findOrFail($claim->employee_id);
            $balance = $this->lockBalance($employee);

            $entry = ToilLedgerEntry::query()->create([
                'employee_id' => $claim->employee_id,
                'entry_type' => 'debit',
                'hours' => (float) $claim->hours,
                'toil_claim_id' => $claim->id,
            ]);

            $balance->decrement('pending_hours', (float) $claim->hours);

            return $entry;
        });
    }

    /**
     * Gives the claim's hours back to the employee. Used both for rejecting/
     * cancelling a still-pending hold (moves hours pending → available) and
     * for cancelling an already-approved claim (the hold was already
     * converted to a permanent debit, which never touched pending_hours
     * again — so only available_hours needs crediting back here).
     */
    public function release(ToilClaim $claim): ToilLedgerEntry
    {
        return DB::transaction(function () use ($claim): ToilLedgerEntry {
            $employee = Employee::query()->findOrFail($claim->employee_id);
            $balance = $this->lockBalance($employee);

            $alreadyDebited = ToilLedgerEntry::query()
                ->where('toil_claim_id', $claim->id)
                ->where('entry_type', 'debit')
                ->exists();

            $entry = ToilLedgerEntry::query()->create([
                'employee_id' => $claim->employee_id,
                'entry_type' => 'release',
                'hours' => (float) $claim->hours,
                'toil_claim_id' => $claim->id,
            ]);

            if (! $alreadyDebited) {
                $balance->decrement('pending_hours', (float) $claim->hours);
            }

            $balance->increment('available_hours', (float) $claim->hours);

            return $entry;
        });
    }

    /**
     * Rebuild available_hours / pending_hours purely from the ledger.
     * The ledger is authoritative; this is the drift-correction path.
     */
    public function reconcileBalance(Employee $employee): ToilBalance
    {
        return DB::transaction(function () use ($employee): ToilBalance {
            $balance = $this->lockBalance($employee);

            $sums = ToilLedgerEntry::query()
                ->where('employee_id', $employee->id)
                ->selectRaw('entry_type, COALESCE(SUM(hours), 0) as total')
                ->groupBy('entry_type')
                ->pluck('total', 'entry_type');

            $credit = (float) ($sums['credit'] ?? 0);
            $hold = (float) ($sums['hold'] ?? 0);
            $release = (float) ($sums['release'] ?? 0);
            $expiry = (float) ($sums['expiry'] ?? 0);
            // Adjustment entries are the one entry_type allowed to carry a signed
            // (positive or negative) `hours` value — there is no admin-facing
            // producer of them in this pass, but the ledger schema and this
            // formula stay forward-compatible with one if it's added later.
            $adjustment = (float) ($sums['adjustment'] ?? 0);

            $available = $credit + $release + $adjustment - $hold - $expiry;
            // pending_hours can't be derived as a simple hold-release-debit
            // aggregate: a release that happens *after* a debit (cancelling an
            // already-approved claim) must not double-subtract pending, since
            // the debit already removed those hours from pending. The claim's
            // own status is the unambiguous source of truth for "still held".
            $pending = (float) ToilClaim::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->sum('hours');

            $balance->update([
                'available_hours' => max(0.0, round($available, 2)),
                'pending_hours' => max(0.0, round($pending, 2)),
            ]);

            return $balance->fresh() ?? $balance;
        });
    }

    /**
     * Expire the unconsumed portion of TOIL credits whose expires_at has
     * passed, using FIFO allocation (oldest credits deemed spent first).
     *
     * @return list<ToilLedgerEntry>
     */
    public function expireDueCredits(CarbonImmutable $asOf): array
    {
        $employeeIds = ToilLedgerEntry::query()
            ->where('entry_type', 'credit')
            ->where('expires_at', '<=', $asOf)
            ->distinct()
            ->pluck('employee_id');

        $expired = [];

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::query()->find($employeeId);

            if ($employee === null) {
                continue;
            }

            $expired = [...$expired, ...$this->expireDueCreditsForEmployee($employee, $asOf)];
        }

        return $expired;
    }

    /**
     * @return list<ToilLedgerEntry>
     */
    private function expireDueCreditsForEmployee(Employee $employee, CarbonImmutable $asOf): array
    {
        return DB::transaction(function () use ($employee, $asOf): array {
            $balance = $this->lockBalance($employee);

            // Everything that has ever reduced the *available* pool, net of
            // reversals: hold moves hours out (whether or not later debited —
            // debit only affects pending_hours, never available_hours again),
            // release moves them back, expiry removes them permanently.
            $consumedTotal = (float) ToilLedgerEntry::query()
                ->where('employee_id', $employee->id)
                ->where('entry_type', 'hold')
                ->sum('hours')
                + (float) ToilLedgerEntry::query()
                    ->where('employee_id', $employee->id)
                    ->where('entry_type', 'expiry')
                    ->sum('hours')
                - (float) ToilLedgerEntry::query()
                    ->where('employee_id', $employee->id)
                    ->where('entry_type', 'release')
                    ->sum('hours');

            $credits = ToilLedgerEntry::query()
                ->where('employee_id', $employee->id)
                ->where('entry_type', 'credit')
                ->orderBy('earned_date')
                ->orderBy('id')
                ->get();

            $cumulativeCredited = 0.0;
            $expiredEntries = [];

            foreach ($credits as $credit) {
                $creditedBefore = $cumulativeCredited;
                $cumulativeCredited += (float) $credit->hours;

                // FIFO allocation: the slice of consumedTotal that falls within
                // this credit's [creditedBefore, cumulativeCredited] window.
                $consumedUpToAndIncluding = min($consumedTotal, $cumulativeCredited);
                $consumedBeforeThisCredit = min($consumedTotal, $creditedBefore);
                $consumedFromThisCredit = $consumedUpToAndIncluding - $consumedBeforeThisCredit;

                $remaining = round((float) $credit->hours - $consumedFromThisCredit, 2);

                if ($remaining > 0.0 && $credit->expires_at !== null && $credit->expires_at->lessThanOrEqualTo($asOf)) {
                    $expiryEntry = ToilLedgerEntry::query()->create([
                        'employee_id' => $employee->id,
                        'entry_type' => 'expiry',
                        'hours' => $remaining,
                        'credit_entry_id' => $credit->id,
                    ]);

                    $balance->decrement('available_hours', $remaining);
                    $expiredEntries[] = $expiryEntry;
                }
            }

            return $expiredEntries;
        });
    }

    private function lockBalance(Employee $employee): ToilBalance
    {
        try {
            ToilBalance::query()->firstOrCreate(['employee_id' => $employee->id]);
        } catch (QueryException) {
            // Unique-constraint race: a concurrent call created it first — fall through and lock it.
        }

        return ToilBalance::query()->where('employee_id', $employee->id)->lockForUpdate()->firstOrFail();
    }
}
