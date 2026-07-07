<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Enums\TaxCalculationMethod;
use App\Enums\TaxDirection;
use App\Http\Requests\Admin\Accounting\StoreTaxTypeRequest;

final readonly class CreateTaxTypeData
{
    public function __construct(
        public string $name,
        public string $code,
        public float $rate,
        public TaxDirection $taxDirection,
        public TaxCalculationMethod $calculationMethod,
        public ?int $outputTaxAccountId,
        public ?int $inputTaxAccountId,
        public ?int $taxPayableAccountId,
        public string $effectiveFrom,
    ) {}

    public static function fromRequest(StoreTaxTypeRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            code: $request->validated('code'),
            rate: (float) $request->validated('rate'),
            taxDirection: TaxDirection::from($request->validated('tax_direction')),
            calculationMethod: TaxCalculationMethod::from($request->validated('calculation_method')),
            outputTaxAccountId: $request->validated('output_tax_account_id') !== null
                ? (int) $request->validated('output_tax_account_id') : null,
            inputTaxAccountId: $request->validated('input_tax_account_id') !== null
                ? (int) $request->validated('input_tax_account_id') : null,
            taxPayableAccountId: $request->validated('tax_payable_account_id') !== null
                ? (int) $request->validated('tax_payable_account_id') : null,
            effectiveFrom: $request->validated('effective_from'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'rate' => $this->rate,
            'tax_direction' => $this->taxDirection->value,
            'calculation_method' => $this->calculationMethod->value,
            'output_tax_account_id' => $this->outputTaxAccountId,
            'input_tax_account_id' => $this->inputTaxAccountId,
            'tax_payable_account_id' => $this->taxPayableAccountId,
            'effective_from' => $this->effectiveFrom,
            'recoverable_percentage' => 100,
            'status' => 'active',
        ];
    }
}
