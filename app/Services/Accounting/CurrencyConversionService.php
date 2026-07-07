<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ExchangeRateType;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;
use DomainException;

final class CurrencyConversionService
{
    public function __construct(
        private readonly FinancialSettingsService $settings,
    ) {}

    public function functionalCurrencyCode(): string
    {
        return $this->settings->get()->functional_currency_code;
    }

    public function resolveRate(string $currencyCode, CarbonInterface|string|null $date = null): float
    {
        if ($currencyCode === $this->functionalCurrencyCode()) {
            return 1.0;
        }

        $currency = Currency::query()->where('code', $currencyCode)->first();

        if ($currency === null) {
            throw new DomainException("Unknown currency code: {$currencyCode}");
        }

        $parsedDate = $date instanceof CarbonInterface ? $date->toDateString() : ($date ?? now()->toDateString());

        $rate = ExchangeRate::query()
            ->where('currency_id', $currency->id)
            ->where('status', 'active')
            ->whereDate('rate_date', '<=', $parsedDate)
            ->orderByDesc('rate_date')
            ->value('rate');

        if ($rate === null) {
            throw new DomainException("No exchange rate for {$currencyCode} on or before {$parsedDate}");
        }

        return (float) $rate;
    }

    /**
     * @return array{functional_amount: float, transaction_amount: float, exchange_rate: float, currency_code: string}
     */
    public function convertToFunctional(
        float $transactionAmount,
        string $currencyCode,
        ?float $exchangeRate = null,
        CarbonInterface|string|null $date = null,
    ): array {
        $functionalCode = $this->functionalCurrencyCode();

        if ($currencyCode === $functionalCode) {
            return [
                'functional_amount' => round($transactionAmount, 2),
                'transaction_amount' => round($transactionAmount, 2),
                'exchange_rate' => 1.0,
                'currency_code' => $functionalCode,
            ];
        }

        $rate = $exchangeRate ?? $this->resolveRate($currencyCode, $date);

        return [
            'functional_amount' => round($transactionAmount * $rate, 2),
            'transaction_amount' => round($transactionAmount, 2),
            'exchange_rate' => $rate,
            'currency_code' => $currencyCode,
        ];
    }

    public function storeRate(
        int $currencyId,
        string $rateDate,
        float $rate,
        ExchangeRateType $rateType = ExchangeRateType::Spot,
        ?int $approvedBy = null,
    ): ExchangeRate {
        return ExchangeRate::query()->updateOrCreate(
            [
                'currency_id' => $currencyId,
                'rate_date' => $rateDate,
                'rate_type' => $rateType,
            ],
            [
                'rate' => $rate,
                'approved_by' => $approvedBy,
                'status' => 'active',
            ],
        );
    }
}
