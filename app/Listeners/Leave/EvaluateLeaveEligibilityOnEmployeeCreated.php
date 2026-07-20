<?php

declare(strict_types=1);

namespace App\Listeners\Leave;

use App\Events\EmployeeCreated;
use App\Services\Leave\LeaveEntitlementAssignmentService;

final class EvaluateLeaveEligibilityOnEmployeeCreated
{
    public function __construct(
        private readonly LeaveEntitlementAssignmentService $assignment,
    ) {}

    public function handle(EmployeeCreated $event): void
    {
        $this->assignment->evaluateForEmployee($event->employee);
    }
}
