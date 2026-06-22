<?php

declare(strict_types=1);

namespace App\Services\Procurement\Contracts;

use App\Models\LandedCostEntry;
use App\Models\PurchaseReturn;

interface ProcurementPostingHook
{
    public function postPurchaseReturn(PurchaseReturn $return): void;

    public function applyLandedCost(LandedCostEntry $entry): void;
}
