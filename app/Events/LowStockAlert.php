<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LowStockAlert
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Inventory $inventory,
        public readonly ProductVariant $variant,
        public readonly int $reorderPoint,
        public readonly int $quantityOnHand,
    ) {}
}
