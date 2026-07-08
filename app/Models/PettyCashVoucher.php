<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PettyCashApprovalStatus;
use App\Enums\PettyCashVoucherType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'voucher_number',
    'petty_cash_register_id',
    'voucher_type',
    'date',
    'amount',
    'expense_account_id',
    'description',
    'approval_required',
    'approval_status',
    'approved_by_user_id',
    'approved_at',
    'rejection_reason',
    'journal_entry_id',
    'created_by',
])]
class PettyCashVoucher extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'voucher_type' => PettyCashVoucherType::class,
            'approval_required' => 'boolean',
            'approval_status' => PettyCashApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(PettyCashRegister::class, 'petty_cash_register_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }
}
