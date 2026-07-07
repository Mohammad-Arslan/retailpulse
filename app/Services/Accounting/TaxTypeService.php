<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateTaxTypeData;
use App\Models\TaxType;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\TaxTypeRepositoryInterface;
use App\Support\TaxTypePresenter;

final class TaxTypeService
{
    public function __construct(
        private readonly TaxTypeRepositoryInterface $taxTypeRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        return [
            'taxTypes' => $this->taxTypeRepository->allOrdered()
                ->map(fn (TaxType $taxType) => TaxTypePresenter::forList($taxType))
                ->values(),
            'postableAccounts' => $this->chartOfAccountRepository->postableOptions(),
        ];
    }

    public function create(CreateTaxTypeData $data): TaxType
    {
        return $this->taxTypeRepository->create($data->toArray());
    }
}
