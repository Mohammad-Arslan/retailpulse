<?php

declare(strict_types=1);

namespace App\Listeners\Procurement;

use App\Events\Procurement\SupplierInvoiceMatched;
use Illuminate\Support\Facades\Log;

final class LogSupplierInvoiceMatched
{
    public function handle(SupplierInvoiceMatched $event): void
    {
        Log::info('Supplier invoice matched.', [
            'invoice_id' => $event->invoice->id,
            'match_status' => $event->matchResult->match_status->value,
        ]);
    }
}
