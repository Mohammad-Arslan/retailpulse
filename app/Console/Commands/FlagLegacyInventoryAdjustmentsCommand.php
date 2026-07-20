<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountingEvent;
use App\Models\StockMovement;
use Illuminate\Console\Command;

final class FlagLegacyInventoryAdjustmentsCommand extends Command
{
    protected $signature = 'accounting:flag-legacy-inventory-adjustments';

    protected $description = 'Flag historical inventory.adjusted journals posted from a negative stock variance for manual finance review (does not repost or rewrite them)';

    public function handle(): int
    {
        $events = AccountingEvent::query()
            ->where('event_type', 'inventory.adjusted')
            ->where('source_type', StockMovement::class)
            ->whereNull('flagged_for_review_at')
            ->get();

        if ($events->isEmpty()) {
            $this->info('No unflagged inventory.adjusted events found.');

            return self::SUCCESS;
        }

        $movements = StockMovement::query()
            ->whereIn('id', $events->pluck('source_id'))
            ->where('qty_delta', '<', 0)
            ->pluck('id')
            ->flip();

        $flagged = 0;

        foreach ($events as $event) {
            if (! $movements->has($event->source_id)) {
                continue;
            }

            $event->update([
                'flagged_for_review_at' => now(),
                'flagged_for_review_reason' => 'Posted before the inventory.adjusted gain/loss sign fix; source stock movement had a negative qty_delta and may have posted as an inventory increase in error.',
            ]);

            $flagged++;
        }

        $this->info("Flagged {$flagged} event(s) for finance review.");

        return self::SUCCESS;
    }
}
