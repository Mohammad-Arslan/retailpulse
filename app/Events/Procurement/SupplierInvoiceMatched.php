<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Models\PoMatchResult;
use App\Models\SupplierInvoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SupplierInvoiceMatched
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupplierInvoice $invoice,
        public readonly PoMatchResult $matchResult,
    ) {}
}
