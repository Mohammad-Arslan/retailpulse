<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateBankAccountData;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\OrganizationEntity;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use App\Support\BankAccountPresenter;

final class BankAccountService
{
    public function __construct(
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
        private readonly BranchRepositoryInterface $branchRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
        private readonly CurrencyRepositoryInterface $currencyRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        return [
            'bankAccounts' => $this->bankAccountRepository->allWithRelations()
                ->map(fn (BankAccount $account) => BankAccountPresenter::forList($account))
                ->values(),
            'branches' => $this->branchRepository->allActive()->map->only(['id', 'name'])->values(),
            'legalEntities' => OrganizationEntity::query()->orderBy('legal_name')->get(['id', 'legal_name']),
            'postableAccounts' => $this->chartOfAccountRepository->postableOptions(),
            'currencies' => $this->currencyRepository->activeOptions(),
        ];
    }

    public function create(CreateBankAccountData $data): BankAccount
    {
        $currency = Currency::query()->where('code', $data->currencyCode)->first();

        return $this->bankAccountRepository->create([
            'branch_id' => $data->branchId,
            'legal_entity_id' => $data->legalEntityId,
            'coa_account_id' => $data->coaAccountId,
            'bank_name' => $data->bankName,
            'account_title' => $data->accountTitle,
            'account_number_masked' => $data->accountNumberMasked,
            'currency_code' => $data->currencyCode,
            'currency_id' => $currency?->id,
            'status' => $data->status,
        ]);
    }
}
