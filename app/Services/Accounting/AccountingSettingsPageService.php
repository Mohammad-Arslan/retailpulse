<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\UpdateFinancialSettingsData;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\FiscalYearRepositoryInterface;
use App\Support\BranchOperationalOptions;
use App\Support\FinancialSettingsPresenter;

final class AccountingSettingsPageService
{
    public function __construct(
        private readonly FinancialSettingsService $settingsService,
        private readonly FiscalYearRepositoryInterface $fiscalYearRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        $settings = $this->settingsService->get();
        $settings->load([
            'retainedEarningsAccount:id,code,name',
            'openingBalanceEquityAccount:id,code,name',
        ]);

        $postableAccounts = $this->chartOfAccountRepository->postableOptions();

        return [
            'settings' => FinancialSettingsPresenter::forPage($settings),
            'fiscalYears' => $this->fiscalYearRepository->allOrdered()
                ->map(fn ($year) => FinancialSettingsPresenter::fiscalYear($year))
                ->values(),
            'postableAccounts' => $postableAccounts,
            'accounts' => $postableAccounts,
            'currencies' => BranchOperationalOptions::currencyOptions(),
            'reopenRequests' => $this->fiscalYearRepository->pendingReopenRequests()
                ->map(fn ($request) => FinancialSettingsPresenter::reopenRequest($request))
                ->values(),
        ];
    }

    public function updateSettings(UpdateFinancialSettingsData $data): void
    {
        $this->settingsService->update($data);
    }
}
