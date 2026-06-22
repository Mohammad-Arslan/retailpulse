<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PurchaseOrderApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PurchaseOrder $purchaseOrder,
    ) {}
}
