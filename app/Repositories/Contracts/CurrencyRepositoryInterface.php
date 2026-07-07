<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Collection;

interface CurrencyRepositoryInterface
{
    /**
     * @return Collection<int, Currency>
     */
    public function allOrdered(): Collection;

    public function create(array $attributes): Currency;

    /**
     * @return Collection<int, ExchangeRate>
     */
    public function recentExchangeRates(int $limit = 50): Collection;

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function activeOptions(): array;
}
