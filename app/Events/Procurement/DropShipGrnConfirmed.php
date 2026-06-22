<?php

declare(strict_types=1);

namespace App\Events\Procurement;

use App\Models\GoodsReceivingNote;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DropShipGrnConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly GoodsReceivingNote $grn,
    ) {}
}
