<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DebitNote;

final class DebitNotePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(DebitNote $note): array
    {
        return [
            'id' => $note->id,
            'reference_no' => $note->reference_no,
            'supplier_name' => $note->supplier?->name,
            'branch_name' => $note->branch?->name,
            'date' => $note->issued_at?->toDateString(),
            'amount' => number_format((float) $note->amount, 2, '.', ''),
            'currency_code' => $note->currency_code,
            'status' => $note->status,
            'notes' => $note->notes,
        ];
    }
}
