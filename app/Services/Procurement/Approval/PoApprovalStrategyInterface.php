<?php

declare(strict_types=1);

namespace App\Services\Procurement\Approval;

use App\Models\PurchaseOrder;
use App\Models\User;

interface PoApprovalStrategyInterface
{
    public function approve(PurchaseOrder $order, User $approver, ?string $managerPin = null): void;
}
