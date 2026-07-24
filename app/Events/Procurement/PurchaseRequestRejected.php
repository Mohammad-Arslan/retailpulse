<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Models\PurchaseRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PurchaseRequestRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PurchaseRequest $purchaseRequest,
    ) {}
}
