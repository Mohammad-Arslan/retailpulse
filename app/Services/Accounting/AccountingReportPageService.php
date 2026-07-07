<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\CostCentreRepositoryInterface;
use App\Repositories\Contracts\FiscalYearRepositoryInterface;

final class AccountingReportPageService
{
    public function __construct(
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
        private readonly CostCentreRepositoryInterface $costCentreRepository,
        private readonly FiscalYearRepositoryInterface $fiscalYearRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'accounts' => $this->chartOfAccountRepository->postableOptions(),
            'costCentres' => $this->costCentreRepository->activeOptions(),
            'fiscalYears' => $this->fiscalYearRepository->options(),
        ];
    }
}
