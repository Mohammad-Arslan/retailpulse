<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\TaxType;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\CreditNoteRepositoryInterface;
use App\Repositories\Contracts\TaxTypeRepositoryInterface;
use App\Support\BranchOperationalOptions;
use App\Support\CreditNotePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CreditNoteListService
{
    public function __construct(
        private readonly CreditNoteRepositoryInterface $creditNoteRepository,
        private readonly BranchRepositoryInterface $branchRepository,
        private readonly TaxTypeRepositoryInterface $taxTypeRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateIndex(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->creditNoteRepository
            ->paginate($filters, $perPage)
            ->through(fn (CreditNote $note) => CreditNotePresenter::forList($note));
    }

    /**
     * @return array<string, mixed>
     */
    public function createFormPayload(): array
    {
        return [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $this->branchRepository->allActive()->map->only(['id', 'name'])->values(),
            'taxTypes' => $this->taxTypeRepository->allOrdered()
                ->where('status', 'active')
                ->map(fn (TaxType $taxType) => $taxType->only(['id', 'name', 'code', 'rate']))
                ->values(),
            'defaultCurrency' => BranchOperationalOptions::defaultCurrency(),
        ];
    }
}
