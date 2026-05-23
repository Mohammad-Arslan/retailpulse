<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InventoryService;
use Illuminate\Console\Command;

final class ReleaseExpiredInventoryReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired-reservations';

    protected $description = 'Release stock reservations that exceeded the configured TTL';

    public function handle(InventoryService $inventory): int
    {
        $released = $inventory->releaseExpiredReservations();

        $this->info("Released {$released} expired reservation(s).");

        return self::SUCCESS;
    }
}
