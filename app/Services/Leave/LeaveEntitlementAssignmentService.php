<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

final class LeaveEntitlementAssignmentService
{
    public function __construct(
        private readonly LeaveService $leaveService,
        private readonly LeaveEligibilityService $eligibility,
    ) {}

    /**
     * Evaluates every active leave type against $employee's currently-effective
     * policy and eligibility, creating an entitlement for every eligible pair
     * that doesn't already have one. Never modifies or removes an existing
     * entitlement — eligibility changes are forward-looking only.
     *
     * @return list<LeaveEntitlement> newly created entitlements
     */
    public function evaluateForEmployee(Employee $employee, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now();
        $hireDate = $employee->hire_date !== null ? CarbonImmutable::parse($employee->hire_date) : $asOf;

        $created = [];

        $leaveTypes = LeaveType::query()->where('status', 'active')->get();

        foreach ($leaveTypes as $leaveType) {
            $policy = $this->leaveService->resolveLeavePolicy($employee, $leaveType, $asOf);

            if ($policy === null) {
                continue;
            }

            if (! $this->eligibility->isEligible($employee, $policy, $asOf)) {
                continue;
            }

            if ($this->leaveService->findEntitlement($employee, $leaveType) !== null) {
                continue;
            }

            $created[] = $this->leaveService->createInitialEntitlement($employee, $leaveType, $policy, $hireDate);
        }

        return $created;
    }
}
