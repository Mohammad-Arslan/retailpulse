<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GrnStatus;
use App\Models\GoodsReceivingNote;
use App\Models\User;
use App\Services\Procurement\SupplierInvoiceService;
use Illuminate\Console\Command;

final class BackfillGrnInvoicesCommand extends Command
{
    protected $signature = 'procurement:backfill-grn-invoices {--user-id= : User ID to attribute invoice creation to}';

    protected $description = 'Create supplier invoices (and ledger entries) for posted GRNs missing an invoice';

    public function handle(SupplierInvoiceService $invoices): int
    {
        $userId = (int) ($this->option('user-id') ?: User::query()->orderBy('id')->value('id'));

        if ($userId <= 0) {
            $this->error('No user found. Pass --user-id=1');

            return self::FAILURE;
        }

        $grns = GoodsReceivingNote::query()
            ->where('status', GrnStatus::Posted)
            ->whereDoesntHave('supplierInvoices')
            ->with(['items.purchaseOrderItem', 'purchaseOrder', 'supplier'])
            ->orderBy('id')
            ->get();

        if ($grns->isEmpty()) {
            $this->info('No posted GRNs without supplier invoices.');

            return self::SUCCESS;
        }

        foreach ($grns as $grn) {
            $invoice = $invoices->createFromGrnIfMissing($grn, $userId);

            if ($invoice === null) {
                $this->warn("Skipped {$grn->reference_no}");

                continue;
            }

            $this->line("{$grn->reference_no} → {$invoice->reference_no} ({$grn->supplier?->name}, {$invoice->total})");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
