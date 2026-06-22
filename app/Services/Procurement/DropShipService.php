<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Events\Procurement\DropShipGrnConfirmed;
use App\Models\GoodsReceivingNote;

final class DropShipService
{
    public function handleVirtualReceive(GoodsReceivingNote $grn): void
    {
        if (! $grn->is_virtual) {
            return;
        }

        $grn->load('purchaseOrder.sale');

        if ($grn->purchaseOrder?->sale_id !== null) {
            event(new DropShipGrnConfirmed($grn));
        }
    }
}
