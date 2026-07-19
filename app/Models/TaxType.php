<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxCalculationMethod;
use App\Enums\TaxDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'name',
    'code',
    'rate',
    'tax_direction',
    'calculation_method',
    'output_tax_account_id',
    'input_tax_account_id',
    'tax_payable_account_id',
    'recoverable_percentage',
    'effective_from',
    'effective_to',
    'status',
])]
class TaxType extends Model
{
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'tax_direction' => TaxDirection::class,
            'calculation_method' => TaxCalculationMethod::class,
            'recoverable_percentage' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function outputTaxAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'output_tax_account_id');
    }

    public function inputTaxAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'input_tax_account_id');
    }

    public function taxPayableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'tax_payable_account_id');
    }
}
