<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Expense\RecurringExpenseScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ProcessRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:process-recurring {--date=}';

    protected $description = 'Generate due recurring expense occurrences and publish accounting events';

    public function handle(RecurringExpenseScheduler $scheduler): int
    {
        $asOf = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))
            : CarbonImmutable::now();

        $processed = $scheduler->processDue($asOf);

        $this->info("Processed {$processed->count()} recurring expense occurrence(s) as of {$asOf->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
