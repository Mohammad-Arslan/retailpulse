<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreatePettyCashRegisterData;
use App\Models\PettyCashRegister;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\PettyCashRegisterRepositoryInterface;
use App\Support\PettyCashPresenter;

final class PettyCashRegisterService
{
    public function __construct(
        private readonly PettyCashRegisterRepositoryInterface $pettyCashRegisterRepository,
        private readonly BranchRepositoryInterface $branchRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        return [
            'registers' => $this->pettyCashRegisterRepository->allWithRelations()
                ->map(fn (PettyCashRegister $register) => PettyCashPresenter::register($register))
                ->values(),
            'recentVouchers' => $this->pettyCashRegisterRepository->recentVouchers()
                ->map(fn ($voucher) => PettyCashPresenter::voucher($voucher))
                ->values(),
            'branches' => $this->branchRepository->allActive()->map->only(['id', 'name'])->values(),
            'postableAccounts' => $this->chartOfAccountRepository->postableOptions(),
        ];
    }

    public function create(CreatePettyCashRegisterData $data): PettyCashRegister
    {
        return $this->pettyCashRegisterRepository->create([
            'branch_id' => $data->branchId,
            'name' => $data->name,
            'coa_account_id' => $data->coaAccountId,
            'opening_balance' => $data->openingBalance,
            'current_balance' => $data->openingBalance,
            'register_mode' => $data->registerMode,
            'status' => 'active',
        ]);
    }
}
