<?php

declare(strict_types=1);

namespace App\Listeners\Procurement;

use App\Events\Procurement\GoodsReceived;
use Illuminate\Support\Facades\Log;

final class LogGoodsReceived
{
    public function handle(GoodsReceived $event): void
    {
        Log::info('Goods received for PO.', [
            'grn_id' => $event->grn->id,
            'po_id' => $event->grn->purchase_order_id,
        ]);
    }
}
