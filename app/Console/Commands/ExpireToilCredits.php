<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Overtime\ToilLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ExpireToilCredits extends Command
{
    protected $signature = 'toil:expire-credits {--as-of= : Process as of this date (Y-m-d), defaults to today}';

    protected $description = 'Expire the unconsumed portion of TOIL credits whose expires_at has passed (FIFO allocation)';

    public function handle(ToilLedgerService $service): int
    {
        $asOfInput = $this->option('as-of');
        $asOf = $asOfInput !== null ? CarbonImmutable::parse($asOfInput) : CarbonImmutable::now();

        $expired = $service->expireDueCredits($asOf);

        if ($expired === []) {
            $this->info("No TOIL credits due for expiry as of {$asOf->toDateString()}.");

            return self::SUCCESS;
        }

        $totalHours = array_sum(array_map(fn ($entry) => (float) $entry->hours, $expired));

        $this->info(sprintf(
            '%d TOIL credit(s) expired totaling %.2f hours as of %s.',
            count($expired),
            $totalHours,
            $asOf->toDateString(),
        ));

        return self::SUCCESS;
    }
}
