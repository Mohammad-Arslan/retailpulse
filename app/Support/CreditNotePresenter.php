<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CreditNote;

final class CreditNotePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(CreditNote $note): array
    {
        return [
            'id' => $note->id,
            'credit_note_number' => $note->credit_note_number,
            'customer_name' => $note->customer?->name,
            'branch_name' => $note->branch?->name,
            'date' => $note->date?->toDateString(),
            'amount' => number_format((float) $note->amount, 2, '.', ''),
            'tax_amount' => number_format((float) $note->tax_amount, 2, '.', ''),
            'status' => $note->status->value,
            'reason' => $note->reason,
        ];
    }
}
