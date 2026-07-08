<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PettyCashRegisterMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id',
    'legal_entity_id',
    'name',
    'coa_account_id',
    'cashier_user_id',
    'opening_balance',
    'current_balance',
    'register_mode',
    'variance_tolerance_amount',
    'approval_threshold_amount',
    'status',
])]
class PettyCashRegister extends Model
{
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'variance_tolerance_amount' => 'decimal:2',
            'approval_threshold_amount' => 'decimal:2',
            'register_mode' => PettyCashRegisterMode::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function coaAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_account_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(PettyCashVoucher::class);
    }
}
