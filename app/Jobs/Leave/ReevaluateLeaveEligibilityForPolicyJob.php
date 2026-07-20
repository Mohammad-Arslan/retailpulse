<?php

declare(strict_types=1);

namespace App\Jobs\Leave;

use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Services\Leave\LeaveEntitlementAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReevaluateLeaveEligibilityForPolicyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly LeavePolicy $policy,
    ) {}

    public function handle(LeaveEntitlementAssignmentService $assignment): void
    {
        Employee::query()
            ->where('status', 'active')
            ->when(
                $this->policy->legal_entity_id !== null,
                fn ($query) => $query->where('legal_entity_id', $this->policy->legal_entity_id),
            )
            ->orderBy('id')
            ->chunkById(200, function ($employees) use ($assignment): void {
                foreach ($employees as $employee) {
                    $assignment->evaluateForEmployee($employee);
                }
            });
    }
}
