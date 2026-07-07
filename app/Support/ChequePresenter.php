<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cheque;

final class ChequePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(Cheque $cheque): array
    {
        return [
            'id' => $cheque->id,
            'type' => $cheque->type->value,
            'cheque_no' => $cheque->cheque_no,
            'bank' => $cheque->bank,
            'amount' => number_format((float) $cheque->amount, 2, '.', ''),
            'currency_code' => $cheque->currency_code,
            'due_date' => $cheque->due_date?->toDateString(),
            'status' => $cheque->status->value,
            'party_type' => $cheque->party_type,
            'party_id' => $cheque->party_id,
        ];
    }
}
