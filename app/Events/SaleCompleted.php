<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SaleCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Sale $sale,
    ) {}
}
