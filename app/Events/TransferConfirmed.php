<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\StockTransfer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TransferConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly StockTransfer $transfer,
    ) {}
}
