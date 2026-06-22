<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Models\SupplierPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SupplierPaymentRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupplierPayment $payment,
    ) {}
}
