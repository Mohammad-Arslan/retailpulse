<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\Procurement\DebitNoteIssued;
use App\Models\DebitNote;
use App\Models\Supplier;
use App\Services\Accounting\AccountingEventService;

final class ProcessAccountingOnDebitNoteIssued
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
    ) {}

    public function handle(DebitNoteIssued $event): void
    {
        $debitNote = $event->debitNote;
        $debitNote->loadMissing(['purchaseReturn.grn', 'supplier']);

        $functionalAmount = (float) $debitNote->functional_amount;

        if ($functionalAmount <= 0) {
            return;
        }

        $userId = (int) ($debitNote->updated_by ?? $debitNote->created_by ?? 0);

        $this->accountingEvents->process(
            'debit_note.issued',
            DebitNote::class,
            (int) $debitNote->id,
            [
                'date' => $debitNote->issued_at?->toDateString() ?? now()->toDateString(),
                'branch_id' => $debitNote->branch_id,
                'warehouse_id' => $debitNote->purchaseReturn?->grn?->warehouse_id,
                'currency_code' => $debitNote->currency_code,
                'exchange_rate' => (float) $debitNote->exchange_rate,
                'amount' => (float) $debitNote->amount,
                'gross_amount' => $functionalAmount,
                'settlement_amount' => $functionalAmount,
                'source_module' => 'procurement',
                'source_number' => $debitNote->reference_no,
                'description' => __('Debit note :ref', ['ref' => $debitNote->reference_no]),
                'party_type' => Supplier::class,
                'party_id' => $debitNote->supplier_id,
                'purchase_return_id' => $debitNote->purchase_return_id,
                'tax_direction' => 'purchase',
                'user_id' => $userId > 0 ? $userId : null,
            ],
            $userId,
        );
    }
}
