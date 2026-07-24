<?php

declare(strict_types=1);

namespace App\Services\Procurement\Approval;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Placeholder for Phase 29 Workflow Engine integration.
 */
final class WorkflowPrApprovalStrategy implements PrApprovalStrategyInterface
{
    public function approve(PurchaseRequest $request, User $approver, ?string $managerPin = null): void
    {
        throw ValidationException::withMessages([
            'approval' => __('Workflow approval is not yet available. Disable feature_flags.procurement.pr_workflow_approval to use PIN approval.'),
        ]);
    }
}
