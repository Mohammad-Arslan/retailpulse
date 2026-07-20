<?php

declare(strict_types=1);

namespace App\Listeners\Leave;

use App\Events\OrgAssignmentChanged;
use App\Services\Leave\LeaveEntitlementAssignmentService;

final class EvaluateLeaveEligibilityOnOrgAssignmentChanged
{
    /**
     * @var list<string>
     */
    private const ELIGIBILITY_RELEVANT_FIELDS = ['grade_id', 'legal_entity_id', 'employment_type'];

    public function __construct(
        private readonly LeaveEntitlementAssignmentService $assignment,
    ) {}

    public function handle(OrgAssignmentChanged $event): void
    {
        if (! in_array($event->field, self::ELIGIBILITY_RELEVANT_FIELDS, true)) {
            return;
        }

        // This listener can fire before the employee's actual update() call
        // persists (org-field changes are recorded/dispatched first, applied
        // after) — read the new value straight off the event rather than the
        // employee model, so eligibility is evaluated against where this
        // field is headed, not its stale in-memory value.
        $employee = $event->employee;
        $employee->{$event->field} = $event->field === 'grade_id' || $event->field === 'legal_entity_id'
            ? ($event->newValue !== null ? (int) $event->newValue : null)
            : $event->newValue;

        $this->assignment->evaluateForEmployee($employee);
    }
}
