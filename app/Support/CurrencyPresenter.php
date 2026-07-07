<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Currency;
use App\Models\ExchangeRate;

final class CurrencyPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(Currency $currency): array
    {
        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => $currency->decimal_places,
            'status' => $currency->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exchangeRate(ExchangeRate $rate): array
    {
        return [
            'id' => $rate->id,
            'currency_code' => $rate->currency?->code,
            'rate_date' => $rate->rate_date?->toDateString(),
            'rate_type' => $rate->rate_type->value,
            'rate' => number_format((float) $rate->rate, 6, '.', ''),
            'status' => $rate->status,
        ];
    }
}
