<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\UpdatePostingRuleSetData;
use App\Models\PostingRuleSet;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\PostingRuleSetRepositoryInterface;
use App\Support\PostingRuleSetPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PostingRuleService
{
    public function __construct(
        private readonly PostingRuleSetRepositoryInterface $postingRuleSetRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateIndex(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->postingRuleSetRepository
            ->paginate($filters, $perPage)
            ->through(fn (PostingRuleSet $set) => PostingRuleSetPresenter::forList($set));
    }

    public function findForEdit(int $id): ?PostingRuleSet
    {
        return $this->postingRuleSetRepository->findByIdWithLines($id);
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function postableAccountOptions(): array
    {
        return $this->chartOfAccountRepository->postableOptions();
    }

    public function update(PostingRuleSet $ruleSet, UpdatePostingRuleSetData $data, int $userId): PostingRuleSet
    {
        $lines = array_map(fn ($line) => $line->toArray(), $data->lines);

        return $this->postingRuleSetRepository->update($ruleSet, $data->attributes, $lines, $userId);
    }
}
