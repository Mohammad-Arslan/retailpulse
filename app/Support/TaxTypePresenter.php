<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TaxType;

final class TaxTypePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(TaxType $taxType): array
    {
        return [
            'id' => $taxType->id,
            'name' => $taxType->name,
            'code' => $taxType->code,
            'rate' => number_format((float) $taxType->rate, 4, '.', ''),
            'tax_direction' => $taxType->tax_direction->value,
            'calculation_method' => $taxType->calculation_method->value,
            'status' => $taxType->status,
            'effective_from' => $taxType->effective_from?->toDateString(),
        ];
    }
}
