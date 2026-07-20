<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Leave\LeaveFiscalYearService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ProcessLeaveYearEnd extends Command
{
    protected $signature = 'leave:process-year-end {--as-of= : Process as of this date (Y-m-d), defaults to today}';

    protected $description = 'Carry forward, expire, or encash leave balances for legal entities/employees whose leave year ends on or before the given date';

    public function handle(LeaveFiscalYearService $service): int
    {
        $asOfInput = $this->option('as-of');
        $asOf = $asOfInput !== null ? CarbonImmutable::parse($asOfInput) : CarbonImmutable::now();

        $runs = [...$service->processDue($asOf), ...$service->expireDueCarriedForward($asOf)];

        if ($runs === []) {
            $this->info("No leave year-end processing due as of {$asOf->toDateString()}.");

            return self::SUCCESS;
        }

        foreach ($runs as $run) {
            $totals = $run->totals_json ?? [];
            $this->info(sprintf(
                'Legal entity #%d — period %s: %d entitlement(s) processed, %.2f carried, %.2f expired, %.2f encashed.',
                $run->legal_entity_id,
                $run->period_label,
                $totals['entitlements_processed'] ?? 0,
                $totals['carried_forward'] ?? 0,
                $totals['expired'] ?? 0,
                $totals['encashed'] ?? 0,
            ));
        }

        return self::SUCCESS;
    }
}
