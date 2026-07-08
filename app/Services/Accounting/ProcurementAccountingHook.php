<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\LandedCostEntry;
use App\Models\PurchaseReturn;
use App\Services\Procurement\Contracts\ProcurementPostingHook;

final class ProcurementAccountingHook implements ProcurementPostingHook
{
    public function __construct(
        private readonly ProcurementPostingService $procurementPosting,
    ) {}

    public function postPurchaseReturn(PurchaseReturn $return): void
    {
        $this->procurementPosting->postPurchaseReturnToGL($return);
    }

    public function applyLandedCost(LandedCostEntry $entry): void
    {
        $this->procurementPosting->applyLandedCost($entry);
    }
}
