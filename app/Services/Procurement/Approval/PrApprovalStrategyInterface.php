<?php

declare(strict_types=1);

namespace App\Services\Procurement\Approval;

use App\Models\PurchaseRequest;
use App\Models\User;

interface PrApprovalStrategyInterface
{
    public function approve(PurchaseRequest $request, User $approver, ?string $managerPin = null): void;
}
