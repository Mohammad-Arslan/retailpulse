<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\LandedCostEntry;
use App\Models\PurchaseReturn;
use App\Services\Procurement\Contracts\ProcurementPostingHook;

final class NullProcurementPostingHook implements ProcurementPostingHook
{
    public function postPurchaseReturn(PurchaseReturn $return): void
    {
        // Phase 11: post Debit AP / Credit Inventory journal entry.
    }

    public function applyLandedCost(LandedCostEntry $entry): void
    {
        // Phase 11: update inventory_cost_layers unit cost.
    }
}
