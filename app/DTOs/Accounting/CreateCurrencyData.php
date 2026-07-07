<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreCurrencyRequest;

final readonly class CreateCurrencyData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $symbol,
        public int $decimalPlaces,
        public string $status,
    ) {}

    public static function fromRequest(StoreCurrencyRequest $request): self
    {
        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            symbol: $request->validated('symbol'),
            decimalPlaces: (int) $request->validated('decimal_places'),
            status: $request->validated('status') ?? 'active',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'decimal_places' => $this->decimalPlaces,
            'status' => $this->status,
        ];
    }
}
