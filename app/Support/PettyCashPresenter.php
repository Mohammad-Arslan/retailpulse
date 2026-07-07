<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PettyCashRegister;
use App\Models\PettyCashVoucher;

final class PettyCashPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function register(PettyCashRegister $register): array
    {
        return [
            'id' => $register->id,
            'name' => $register->name,
            'branch_name' => $register->branch?->name,
            'current_balance' => number_format((float) $register->current_balance, 2, '.', ''),
            'register_mode' => $register->register_mode->value,
            'status' => $register->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function voucher(PettyCashVoucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'register_name' => $voucher->register?->name,
            'voucher_type' => $voucher->voucher_type->value,
            'date' => $voucher->date?->toDateString(),
            'amount' => number_format((float) $voucher->amount, 2, '.', ''),
        ];
    }
}
