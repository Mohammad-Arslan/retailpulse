<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\Accounting\CreditNoteIssued;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Services\Accounting\AccountingEventService;

final class PostCreditNoteToAccounting
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
    ) {}

    public function handle(CreditNoteIssued $event): void
    {
        $creditNote = $event->creditNote;

        if ($creditNote->journal_entry_id !== null) {
            return;
        }

        try {
            $accountingEvent = $this->accountingEvents->process(
                'credit_note.issued',
                CreditNote::class,
                $creditNote->id,
                [
                    'date' => $creditNote->date->toDateString(),
                    'branch_id' => $creditNote->branch_id,
                    'gross_amount' => (float) $creditNote->amount + (float) $creditNote->tax_amount,
                    'net_amount' => (float) $creditNote->amount,
                    'tax_amount' => (float) $creditNote->tax_amount,
                    'settlement_amount' => (float) $creditNote->amount + (float) $creditNote->tax_amount,
                    'currency_code' => $creditNote->currency_code,
                    'exchange_rate' => $creditNote->exchange_rate,
                    'party_type' => Customer::class,
                    'party_id' => $creditNote->customer_id,
                    'tax_direction' => 'sales',
                    'description' => "Credit note {$creditNote->credit_note_number}",
                    'source_number' => $creditNote->credit_note_number,
                    'user_id' => $creditNote->created_by ?? 1,
                ],
                (int) ($creditNote->created_by ?? 1),
            );

            $creditNote->update(['journal_entry_id' => $accountingEvent->journal_entry_id]);
        } catch (\Throwable) {
            // Posting rules may not be configured yet.
        }
    }
}
