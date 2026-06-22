<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Models\PurchaseReturn;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PurchaseReturnApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PurchaseReturn $purchaseReturn,
    ) {}
}
