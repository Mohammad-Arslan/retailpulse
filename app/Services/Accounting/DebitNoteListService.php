<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\DebitNote;
use App\Models\Supplier;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\DebitNoteRepositoryInterface;
use App\Support\BranchOperationalOptions;
use App\Support\DebitNotePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class DebitNoteListService
{
    public function __construct(
        private readonly DebitNoteRepositoryInterface $debitNoteRepository,
        private readonly BranchRepositoryInterface $branchRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateIndex(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->debitNoteRepository
            ->paginate($filters, $perPage)
            ->through(fn (DebitNote $note) => DebitNotePresenter::forList($note));
    }

    /**
     * @return array<string, mixed>
     */
    public function createFormPayload(): array
    {
        return [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $this->branchRepository->allActive()->map->only(['id', 'name'])->values(),
            'defaultCurrency' => BranchOperationalOptions::defaultCurrency(),
        ];
    }
}
