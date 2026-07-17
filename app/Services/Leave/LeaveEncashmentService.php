<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveEncashment;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveEncashmentService
{
    public function __construct(
        private readonly LeaveService $leaveService,
    ) {}

    public function requestEncashment(
        Employee $employee,
        LeaveType $leaveType,
        float $days,
        ?string $reason = null,
    ): LeaveEncashment {
        $this->leaveService->assertLeaveTypeActive($leaveType);

        if ($leaveType->code === 'TOIL') {
            throw ValidationException::withMessages([
                'leave_type_id' => __('TOIL balances are cashed out via the TOIL cash claim flow, not generic leave encashment.'),
            ]);
        }

        if ($days <= 0) {
            throw ValidationException::withMessages([
                'days' => __('Encashment days must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($employee, $leaveType, $days, $reason): LeaveEncashment {
            // Serialize concurrent encashment/leave requests for this employee so the
            // balance check below can never be bypassed by a concurrent double-spend.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            $policy = $this->leaveService->resolveLeavePolicy($employee, $leaveType, CarbonImmutable::now());

            if ($policy === null || ! $policy->encashment_allowed) {
                throw ValidationException::withMessages([
                    'leave_type_id' => __('Leave encashment is not allowed for this leave type.'),
                ]);
            }

            if ($policy->encashment_max_days !== null && $days > (float) $policy->encashment_max_days) {
                throw ValidationException::withMessages([
                    'days' => __('Leave encashment cannot exceed :max days per request.', [
                        'max' => rtrim(rtrim((string) $policy->encashment_max_days, '0'), '.'),
                    ]),
                ]);
            }

            $componentCode = $leaveType->payroll_encashment_component_code;
            if ($componentCode === null || $componentCode === '') {
                throw new DomainException(__('Leave type :code requires a payroll encashment component but none is configured.', [
                    'code' => $leaveType->code,
                ]));
            }

            $entitlement = $this->lockEntitlement(
                $this->leaveService->resolveEntitlement($employee, $leaveType),
            );

            if ($days > (float) $entitlement->remaining_days) {
                throw ValidationException::withMessages([
                    'days' => __('Leave encashment of :days days exceeds the available balance of :remaining days.', [
                        'days' => (string) $days,
                        'remaining' => (string) $entitlement->remaining_days,
                    ]),
                ]);
            }

            $requiresApproval = $policy->encashment_requires_approval;
            $status = $requiresApproval ? 'pending' : 'approved';

            $approvalChain = [];
            if (! $requiresApproval) {
                $approvalChain[] = [
                    'action' => 'auto_approved',
                    'reason' => 'policy_does_not_require_approval',
                    'at' => now()->toIso8601String(),
                ];
            }

            $encashment = LeaveEncashment::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'leave_policy_id' => $policy->id,
                'fiscal_year_id' => $entitlement->fiscal_year_id,
                'days' => $days,
                'payroll_component_code' => $componentCode,
                'reason' => $reason,
                'status' => $status,
                'approved_at' => $requiresApproval ? null : now(),
                'approval_chain_json' => $approvalChain,
            ]);

            if (! $requiresApproval) {
                $entitlement->increment('encashed_days', $days);
            }

            return $encashment;
        });
    }

    public function approve(LeaveEncashment $encashment, int $approvedByUserId): LeaveEncashment
    {
        $this->assertPending($encashment);

        return DB::transaction(function () use ($encashment, $approvedByUserId): LeaveEncashment {
            $encashment->loadMissing(['employee', 'leaveType']);

            $entitlement = $this->lockEntitlement(
                $this->leaveService->resolveEntitlement($encashment->employee, $encashment->leaveType, $encashment->fiscal_year_id),
            );

            if ((float) $encashment->days > (float) $entitlement->remaining_days) {
                throw new DomainException(__('Approving this encashment would exceed the available leave balance.'));
            }

            $entitlement->increment('encashed_days', (float) $encashment->days);

            $chain = is_array($encashment->approval_chain_json) ? $encashment->approval_chain_json : [];
            $chain[] = [
                'action' => 'approved',
                'by_user_id' => $approvedByUserId,
                'at' => now()->toIso8601String(),
            ];

            $encashment->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approval_chain_json' => $chain,
            ]);

            return $encashment->fresh(['employee', 'leaveType']) ?? $encashment;
        });
    }

    public function reject(LeaveEncashment $encashment, int $rejectedByUserId, ?string $reason = null): LeaveEncashment
    {
        $this->assertPending($encashment);

        $chain = is_array($encashment->approval_chain_json) ? $encashment->approval_chain_json : [];
        $chain[] = [
            'action' => 'rejected',
            'by_user_id' => $rejectedByUserId,
            'at' => now()->toIso8601String(),
            'reason' => $reason,
        ];

        $encashment->update([
            'status' => 'rejected',
            'approval_chain_json' => $chain,
        ]);

        return $encashment->fresh(['employee', 'leaveType']) ?? $encashment;
    }

    public function cancel(LeaveEncashment $encashment, int $cancelledByUserId): LeaveEncashment
    {
        if ($encashment->status === 'approved') {
            return DB::transaction(function () use ($encashment, $cancelledByUserId): LeaveEncashment {
                $encashment->loadMissing(['employee', 'leaveType']);

                $entitlement = $this->lockEntitlement(
                    $this->leaveService->resolveEntitlement($encashment->employee, $encashment->leaveType, $encashment->fiscal_year_id),
                );

                $entitlement->decrement('encashed_days', (float) $encashment->days);

                $chain = is_array($encashment->approval_chain_json) ? $encashment->approval_chain_json : [];
                $chain[] = [
                    'action' => 'cancelled',
                    'by_user_id' => $cancelledByUserId,
                    'at' => now()->toIso8601String(),
                ];

                $encashment->update([
                    'status' => 'cancelled',
                    'approval_chain_json' => $chain,
                ]);

                return $encashment->fresh(['employee', 'leaveType']) ?? $encashment;
            });
        }

        $this->assertPending($encashment);

        $chain = is_array($encashment->approval_chain_json) ? $encashment->approval_chain_json : [];
        $chain[] = [
            'action' => 'cancelled',
            'by_user_id' => $cancelledByUserId,
            'at' => now()->toIso8601String(),
        ];

        $encashment->update([
            'status' => 'cancelled',
            'approval_chain_json' => $chain,
        ]);

        return $encashment->fresh(['employee', 'leaveType']) ?? $encashment;
    }

    private function lockEntitlement(LeaveEntitlement $entitlement): LeaveEntitlement
    {
        return LeaveEntitlement::query()->whereKey($entitlement->id)->lockForUpdate()->firstOrFail();
    }

    private function assertPending(LeaveEncashment $encashment): void
    {
        if ($encashment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('Only pending leave encashment requests can be updated.'),
            ]);
        }
    }
}
