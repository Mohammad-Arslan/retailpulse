<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateAccountMappingData;
use App\DTOs\Accounting\UpdateAccountMappingData;
use App\Models\AccountMapping;
use App\Repositories\Contracts\AccountMappingRepositoryInterface;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Support\AccountMappingPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AccountMappingService
{
    public function __construct(
        private readonly AccountMappingRepositoryInterface $accountMappingRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateIndex(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->accountMappingRepository
            ->paginate($filters, $perPage)
            ->through(fn (AccountMapping $mapping) => AccountMappingPresenter::forList($mapping));
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function postableAccountOptions(): array
    {
        return $this->chartOfAccountRepository->postableOptions();
    }

    public function create(CreateAccountMappingData $data): AccountMapping
    {
        return $this->accountMappingRepository->create($data->toArray());
    }

    public function update(AccountMapping $mapping, UpdateAccountMappingData $data): AccountMapping
    {
        return $this->accountMappingRepository->update($mapping, $data->attributes);
    }

    public function delete(AccountMapping $mapping): void
    {
        $this->accountMappingRepository->delete($mapping);
    }
}
