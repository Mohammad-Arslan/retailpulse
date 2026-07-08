<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\Procurement\GoodsReceived;
use App\Services\Accounting\ProcurementPostingService;

final class ProcessAccountingOnGoodsReceived
{
    public function __construct(
        private readonly ProcurementPostingService $procurementPosting,
    ) {}

    public function handle(GoodsReceived $event): void
    {
        $this->procurementPosting->postGrnToGL($event->grn);
    }
}
