<?php

declare(strict_types=1);

namespace App\Services\Procurement\Approval;

use App\Models\SystemSetting;

final class PoApprovalStrategyFactory
{
    public function __construct(
        private readonly PinPoApprovalStrategy $pinStrategy,
        private readonly WorkflowPoApprovalStrategy $workflowStrategy,
    ) {}

    public function make(): PoApprovalStrategyInterface
    {
        if ((bool) SystemSetting::get('feature_flags', 'procurement.workflow_approval', false)) {
            return $this->workflowStrategy;
        }

        return $this->pinStrategy;
    }
}
