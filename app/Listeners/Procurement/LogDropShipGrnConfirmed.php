<?php

declare(strict_types=1);

namespace App\Listeners\Procurement;

use App\Events\Procurement\DropShipGrnConfirmed;
use Illuminate\Support\Facades\Log;

final class LogDropShipGrnConfirmed
{
    public function handle(DropShipGrnConfirmed $event): void
    {
        Log::info('Drop-ship GRN confirmed; customer invoice generation deferred to Phase 11.', [
            'grn_id' => $event->grn->id,
            'sale_id' => $event->grn->purchaseOrder?->sale_id,
        ]);
    }
}
