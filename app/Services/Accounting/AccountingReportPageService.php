<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\CostCentreRepositoryInterface;
use App\Repositories\Contracts\FiscalYearRepositoryInterface;
use App\Support\AccountingAuditTypes;

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
            'auditEntityTypes' => collect(AccountingAuditTypes::classNames())
                ->map(fn (string $class) => [
                    'value' => $class,
                    'label' => AccountingAuditTypes::shortName($class),
                ])
                ->sortBy('label')
                ->values()
                ->all(),
            'auditEvents' => [
                ['value' => 'created', 'label' => 'Created'],
                ['value' => 'updated', 'label' => 'Updated'],
                ['value' => 'deleted', 'label' => 'Deleted'],
            ],
        ];
    }
}
