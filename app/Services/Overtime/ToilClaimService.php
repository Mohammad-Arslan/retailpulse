<?php

declare(strict_types=1);

namespace App\Services\Overtime;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ToilClaim;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Thin orchestration around ToilLedgerService for TOIL claims. A claim is a
 * unified record for both claim types (leave and cash) — the ledger's
 * toil_claim_id always points to a ToilClaim row regardless of which type
 * caused the hold/debit/release, and approve/reject/cancel are shared across
 * both since the ledger operation itself doesn't differ by claim type.
 */
final class ToilClaimService
{
    public function __construct(
        private readonly ToilLedgerService $ledger,
    ) {}

    public function holdForLeaveClaim(Employee $employee, LeaveRequest $leaveRequest, float $hours): ToilClaim
    {
        return DB::transaction(function () use ($employee, $leaveRequest, $hours): ToilClaim {
            $claim = ToilClaim::query()->create([
                'employee_id' => $employee->id,
                'claim_type' => 'leave',
                'hours' => $hours,
                'status' => 'pending',
                'leave_request_id' => $leaveRequest->id,
            ]);

            $this->ledger->holdForClaim($employee, $hours, $claim);

            return $claim;
        });
    }

    public function requestCashClaim(Employee $employee, LeaveType $leaveType, float $hours, ?string $reason = null): ToilClaim
    {
        if (! $leaveType->allow_cash_claim) {
            throw ValidationException::withMessages([
                'claim_type' => __('Cash claims are disabled for this leave type.'),
            ]);
        }

        if ($hours <= 0) {
            throw ValidationException::withMessages([
                'hours' => __('Cash claim hours must be greater than zero.'),
            ]);
        }

        $componentCode = $leaveType->payroll_toil_payout_component_code;

        if ($componentCode === null || $componentCode === '') {
            throw new DomainException(__('Leave type :code requires a payroll TOIL payout component but none is configured.', [
                'code' => $leaveType->code,
            ]));
        }

        return DB::transaction(function () use ($employee, $hours, $reason, $componentCode): ToilClaim {
            // Serialize concurrent claims for this employee, same as leave/encashment requests.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $claim = ToilClaim::query()->create([
                'employee_id' => $employee->id,
                'claim_type' => 'cash',
                'hours' => $hours,
                'status' => 'pending',
                'payroll_component_code' => $componentCode,
                'reason' => $reason,
            ]);

            $this->ledger->holdForClaim($employee, $hours, $claim);

            return $claim;
        });
    }

    public function approve(ToilClaim $claim, int $approvedByUserId): ToilClaim
    {
        $this->assertPending($claim);

        return DB::transaction(function () use ($claim, $approvedByUserId): ToilClaim {
            $this->ledger->debit($claim);

            $claim->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approval_chain_json' => $this->appendChain($claim, 'approved', $approvedByUserId),
            ]);

            return $claim->fresh() ?? $claim;
        });
    }

    public function reject(ToilClaim $claim, int $rejectedByUserId, ?string $reason = null): ToilClaim
    {
        $this->assertPending($claim);

        return DB::transaction(function () use ($claim, $rejectedByUserId, $reason): ToilClaim {
            $this->ledger->release($claim);

            $claim->update([
                'status' => 'rejected',
                'approval_chain_json' => $this->appendChain($claim, 'rejected', $rejectedByUserId, $reason),
            ]);

            return $claim->fresh() ?? $claim;
        });
    }

    public function cancel(ToilClaim $claim, int $cancelledByUserId): ToilClaim
    {
        return DB::transaction(function () use ($claim, $cancelledByUserId): ToilClaim {
            $this->ledger->release($claim);

            $claim->update([
                'status' => 'cancelled',
                'approval_chain_json' => $this->appendChain($claim, 'cancelled', $cancelledByUserId),
            ]);

            return $claim->fresh() ?? $claim;
        });
    }

    private function assertPending(ToilClaim $claim): void
    {
        if ($claim->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('Only pending TOIL claims can be updated.'),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appendChain(ToilClaim $claim, string $action, int $userId, ?string $reason = null): array
    {
        $chain = is_array($claim->approval_chain_json) ? $claim->approval_chain_json : [];
        $chain[] = [
            'action' => $action,
            'by_user_id' => $userId,
            'at' => now()->toIso8601String(),
            'reason' => $reason,
        ];

        return $chain;
    }
}
