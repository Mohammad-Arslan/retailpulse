<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Enums\ExchangeRateType;
use App\Http\Requests\Admin\Accounting\StoreExchangeRateRequest;

final readonly class CreateExchangeRateData
{
    public function __construct(
        public int $currencyId,
        public string $rateDate,
        public float $rate,
        public ExchangeRateType $rateType,
    ) {}

    public static function fromRequest(StoreExchangeRateRequest $request): self
    {
        return new self(
            currencyId: (int) $request->validated('currency_id'),
            rateDate: $request->validated('rate_date'),
            rate: (float) $request->validated('rate'),
            rateType: ExchangeRateType::from($request->validated('rate_type') ?? 'spot'),
        );
    }
}
