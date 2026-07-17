<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Overtime\ToilLedgerService;
use Illuminate\Console\Command;

final class ReconcileToilBalances extends Command
{
    protected $signature = 'toil:reconcile-balances';

    protected $description = 'Rebuild toil_balances purely from toil_ledger_entries for every employee with ledger activity — a drift-correction safety net, since the ledger is always the source of truth';

    public function handle(ToilLedgerService $service): int
    {
        $employeeIds = Employee::query()
            ->whereHas('toilLedgerEntries')
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            $this->info('No employees with TOIL ledger activity to reconcile.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::query()->find($employeeId);

            if ($employee === null) {
                continue;
            }

            $service->reconcileBalance($employee);
            $count++;
        }

        $this->info("Reconciled TOIL balances for {$count} employee(s).");

        return self::SUCCESS;
    }
}
