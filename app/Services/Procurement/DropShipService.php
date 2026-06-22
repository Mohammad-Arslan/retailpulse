<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\GoodsReceivingNote;
use App\Models\SystemSetting;

final class DropShipService
{
    /**
     * Handle virtual receive for drop-ship POs — no inventory change.
     * Future: trigger customer invoice and shipment notification.
     */
    public function handleVirtualReceive(GoodsReceivingNote $grn): void
    {
        if (! $grn->is_virtual) {
            return;
        }

        $grn->load('purchaseOrder.sale');

        // Configuration-driven fulfillment hooks for Phase 29+ integrations
        $enabled = (bool) SystemSetting::get('procurement', 'drop_ship_auto_invoice', false);

        if ($enabled && $grn->purchaseOrder?->sale_id !== null) {
            // Phase 11/29: dispatch customer invoice event
        }
    }
}
