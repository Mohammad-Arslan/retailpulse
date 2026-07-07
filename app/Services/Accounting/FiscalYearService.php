<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateFiscalYearData;
use App\DTOs\Accounting\UpdateFiscalYearData;
use App\Models\FiscalYear;
use App\Repositories\Contracts\FiscalYearRepositoryInterface;

final class FiscalYearService
{
    public function __construct(
        private readonly FiscalYearRepositoryInterface $fiscalYearRepository,
    ) {}

    public function create(CreateFiscalYearData $data): FiscalYear
    {
        return $this->fiscalYearRepository->create($data->toArray());
    }

    public function update(FiscalYear $fiscalYear, UpdateFiscalYearData $data): FiscalYear
    {
        return $this->fiscalYearRepository->update($fiscalYear, $data->attributes);
    }
}
