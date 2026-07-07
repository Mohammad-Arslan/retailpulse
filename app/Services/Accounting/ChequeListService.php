<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Cheque;
use App\Models\Customer;
use App\Repositories\Contracts\ChequeRepositoryInterface;
use App\Support\BranchOperationalOptions;
use App\Support\ChequePresenter;

final class ChequeListService
{
    public function __construct(
        private readonly ChequeRepositoryInterface $chequeRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        return [
            'cheques' => $this->chequeRepository->recent()
                ->map(fn (Cheque $cheque) => ChequePresenter::forList($cheque))
                ->values(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'defaultCurrency' => BranchOperationalOptions::defaultCurrency(),
        ];
    }
}
