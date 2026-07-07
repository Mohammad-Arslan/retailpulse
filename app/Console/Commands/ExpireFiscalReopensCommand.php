<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\FiscalCloseService;
use Illuminate\Console\Command;

final class ExpireFiscalReopensCommand extends Command
{
    protected $signature = 'accounting:expire-fiscal-reopens';

    protected $description = 'Re-close fiscal years whose reopening window has expired';

    public function handle(FiscalCloseService $fiscalCloseService): int
    {
        $expiredIds = $fiscalCloseService->expireReopenedFiscalYears();

        if ($expiredIds === []) {
            $this->info('No expired fiscal year reopen windows found.');

            return self::SUCCESS;
        }

        $this->info('Re-closed fiscal year(s): '.implode(', ', $expiredIds));

        return self::SUCCESS;
    }
}
