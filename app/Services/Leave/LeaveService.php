<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveService
{
    public function requestLeave(
        Employee $employee,
        LeaveType $leaveType,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?string $reason = null,
    ): LeaveRequest {
        $this->assertLeaveTypeActive($leaveType);

        if ($endDate->lessThan($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => __('The end date must be on or after the start date.'),
            ]);
        }

        $days = (float) $startDate->diffInDays($endDate) + 1;

        return LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $days,
            'reason' => $reason,
            'status' => 'pending',
            'approval_chain_json' => [],
        ]);
    }

    public function approve(LeaveRequest $request, int $approvedByUserId): LeaveRequest
    {
        $this->assertPending($request);

        return DB::transaction(function () use ($request, $approvedByUserId): LeaveRequest {
            $request->loadMissing(['employee', 'leaveType']);

            $entitlement = $this->resolveEntitlement(
                $request->employee,
                $request->leaveType,
            );

            $entitlement->increment('used_days', (float) $request->days);

            $chain = is_array($request->approval_chain_json) ? $request->approval_chain_json : [];
            $chain[] = [
                'action' => 'approved',
                'by_user_id' => $approvedByUserId,
                'at' => now()->toIso8601String(),
            ];

            $request->update([
                'status' => 'approved',
                'approval_chain_json' => $chain,
            ]);

            return $request->fresh(['employee', 'leaveType']) ?? $request;
        });
    }

    public function reject(LeaveRequest $request, int $rejectedByUserId, ?string $reason = null): LeaveRequest
    {
        $this->assertPending($request);

        $chain = is_array($request->approval_chain_json) ? $request->approval_chain_json : [];
        $chain[] = [
            'action' => 'rejected',
            'by_user_id' => $rejectedByUserId,
            'at' => now()->toIso8601String(),
            'reason' => $reason,
        ];

        $request->update([
            'status' => 'rejected',
            'approval_chain_json' => $chain,
        ]);

        return $request->fresh(['employee', 'leaveType']) ?? $request;
    }

    public function cancel(LeaveRequest $request, int $cancelledByUserId): LeaveRequest
    {
        if ($request->status === 'approved') {
            return DB::transaction(function () use ($request, $cancelledByUserId): LeaveRequest {
                $request->loadMissing(['employee', 'leaveType']);

                $entitlement = $this->resolveEntitlement(
                    $request->employee,
                    $request->leaveType,
                );

                $entitlement->decrement('used_days', (float) $request->days);

                $chain = is_array($request->approval_chain_json) ? $request->approval_chain_json : [];
                $chain[] = [
                    'action' => 'cancelled',
                    'by_user_id' => $cancelledByUserId,
                    'at' => now()->toIso8601String(),
                ];

                $request->update([
                    'status' => 'cancelled',
                    'approval_chain_json' => $chain,
                ]);

                return $request->fresh(['employee', 'leaveType']) ?? $request;
            });
        }

        $this->assertPending($request);

        $chain = is_array($request->approval_chain_json) ? $request->approval_chain_json : [];
        $chain[] = [
            'action' => 'cancelled',
            'by_user_id' => $cancelledByUserId,
            'at' => now()->toIso8601String(),
        ];

        $request->update([
            'status' => 'cancelled',
            'approval_chain_json' => $chain,
        ]);

        return $request->fresh(['employee', 'leaveType']) ?? $request;
    }

    public function resolvePayrollDeductionComponent(Employee $employee, LeaveRequest $request): ?string
    {
        $request->loadMissing('leaveType');
        $leaveType = $request->leaveType;

        if ($leaveType === null || ! $leaveType->affects_payroll) {
            return null;
        }

        $entitlement = $this->findEntitlement($employee, $leaveType);
        $exceedsBalance = $leaveType->is_paid && (
            $entitlement === null
                ? (float) $request->days > 0
                : (float) $entitlement->remaining_days < 0
        );

        $needsDeduction = ! $leaveType->is_paid || $exceedsBalance;

        if (! $needsDeduction) {
            return null;
        }

        $componentCode = $leaveType->payroll_deduction_component_code;

        if ($componentCode === null || $componentCode === '') {
            throw new DomainException(__('Leave type :code requires a payroll deduction component but none is configured.', [
                'code' => $leaveType->code,
            ]));
        }

        return $componentCode;
    }

    private function resolveEntitlement(Employee $employee, LeaveType $leaveType, ?int $fiscalYearId = null): LeaveEntitlement
    {
        $entitlement = $this->findEntitlement($employee, $leaveType, $fiscalYearId);

        if ($entitlement !== null) {
            return $entitlement;
        }

        return LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => $fiscalYearId,
            'accrued_days' => 0,
            'used_days' => 0,
            'carried_forward_days' => 0,
        ]);
    }

    private function findEntitlement(
        Employee $employee,
        LeaveType $leaveType,
        ?int $fiscalYearId = null,
    ): ?LeaveEntitlement {
        return LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->when(
                $fiscalYearId === null,
                fn ($query) => $query->whereNull('fiscal_year_id'),
                fn ($query) => $query->where('fiscal_year_id', $fiscalYearId),
            )
            ->first();
    }

    private function assertLeaveTypeActive(LeaveType $leaveType): void
    {
        if ($leaveType->status !== 'active') {
            throw ValidationException::withMessages([
                'leave_type_id' => __('This leave type is not active.'),
            ]);
        }
    }

    private function assertPending(LeaveRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('Only pending leave requests can be updated.'),
            ]);
        }
    }
}
