<?php

declare(strict_types=1);

namespace App\Services\Procurement\Approval;

use App\Models\SystemSetting;

final class PrApprovalStrategyFactory
{
    public function __construct(
        private readonly PinPrApprovalStrategy $pinStrategy,
        private readonly WorkflowPrApprovalStrategy $workflowStrategy,
    ) {}

    public function make(): PrApprovalStrategyInterface
    {
        if ((bool) SystemSetting::get('feature_flags', 'procurement.pr_workflow_approval', false)) {
            return $this->workflowStrategy;
        }

        return $this->pinStrategy;
    }
}
