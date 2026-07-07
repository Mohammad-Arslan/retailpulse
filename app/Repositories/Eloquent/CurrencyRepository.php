<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use Illuminate\Support\Collection;

final class CurrencyRepository implements CurrencyRepositoryInterface
{
    public function allOrdered(): Collection
    {
        return Currency::query()->orderBy('code')->get();
    }

    public function create(array $attributes): Currency
    {
        return Currency::query()->create($attributes);
    }

    public function recentExchangeRates(int $limit = 50): Collection
    {
        return ExchangeRate::query()
            ->with('currency:id,code')
            ->orderByDesc('rate_date')
            ->limit($limit)
            ->get();
    }

    public function activeOptions(): array
    {
        return Currency::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Currency $currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
            ])
            ->values()
            ->all();
    }
}
