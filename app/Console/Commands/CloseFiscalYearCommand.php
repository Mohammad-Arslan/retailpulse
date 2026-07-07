<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Services\Accounting\FiscalCloseService;
use Illuminate\Console\Command;

final class CloseFiscalYearCommand extends Command
{
    protected $signature = 'accounting:close-fiscal-year
                            {fiscal_year : Fiscal year ID to close}
                            {--user=1 : User ID performing the close}';

    protected $description = 'Validate, lock, and close a fiscal year with retained earnings transfer';

    public function handle(FiscalCloseService $closeService): int
    {
        $fiscalYear = FiscalYear::query()->findOrFail((int) $this->argument('fiscal_year'));
        $userId = (int) $this->option('user');

        $errors = $closeService->validate($fiscalYear);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $this->confirm("Close fiscal year \"{$fiscalYear->name}\"?", true)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        try {
            $closeService->close($fiscalYear, $userId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Fiscal year \"{$fiscalYear->name}\" closed successfully.");

        return self::SUCCESS;
    }
}
