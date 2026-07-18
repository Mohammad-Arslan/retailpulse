<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ProcessLeaveAccrual extends Command
{
    protected $signature = 'leave:process-accrual {--as-of= : Process as of this date (Y-m-d), defaults to today}';

    protected $description = 'Post due monthly_accrual/per_worked_hours leave accrual for active employees (fixed_annual is granted at hire and at year-end, not here)';

    public function handle(LeaveService $service): int
    {
        $asOfInput = $this->option('as-of');
        $asOf = $asOfInput !== null ? CarbonImmutable::parse($asOfInput) : CarbonImmutable::now();

        $result = $service->processAccrual($asOf);

        if ($result['processed'] === 0) {
            $this->info("No leave accrual due as of {$asOf->toDateString()}.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d entitlement(s) accrued, %.2f total days granted.',
            $result['processed'],
            $result['total_granted'],
        ));

        return self::SUCCESS;
    }
}
