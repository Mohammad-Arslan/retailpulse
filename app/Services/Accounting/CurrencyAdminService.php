<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateCurrencyData;
use App\DTOs\Accounting\CreateExchangeRateData;
use App\Models\Currency;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use App\Support\CurrencyPresenter;

final class CurrencyAdminService
{
    public function __construct(
        private readonly CurrencyRepositoryInterface $currencyRepository,
        private readonly CurrencyConversionService $currencyConversion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        return [
            'currencies' => $this->currencyRepository->allOrdered()
                ->map(fn (Currency $currency) => CurrencyPresenter::forList($currency))
                ->values(),
            'exchangeRates' => $this->currencyRepository->recentExchangeRates()
                ->map(fn ($rate) => CurrencyPresenter::exchangeRate($rate))
                ->values(),
            'functionalCurrency' => $this->currencyConversion->functionalCurrencyCode(),
        ];
    }

    public function create(CreateCurrencyData $data): Currency
    {
        return $this->currencyRepository->create($data->toArray());
    }

    public function storeRate(CreateExchangeRateData $data, int $userId): void
    {
        $this->currencyConversion->storeRate(
            $data->currencyId,
            $data->rateDate,
            $data->rate,
            $data->rateType,
            $userId,
        );
    }
}
