<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Models\SupplierInvoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SupplierInvoiceCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupplierInvoice $invoice,
    ) {}
}
