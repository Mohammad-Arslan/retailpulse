<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\DuplicatePostingRuleSetResult;
use App\DTOs\Accounting\StorePostingRuleSetData;
use App\DTOs\Accounting\UpdatePostingRuleSetData;
use App\Models\PostingRuleSet;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\PostingRuleSetRepositoryInterface;
use App\Support\PostingRuleSetPresenter;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PostingRuleService
{
    public function __construct(
        private readonly PostingRuleSetRepositoryInterface $postingRuleSetRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
        private readonly PostingRuleValidationService $postingRuleValidationService,
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
        $this->postingRuleValidationService->assertHasDebitAndCredit($data->lines);

        $lines = array_map(fn ($line) => $line->toArray(), $data->lines);

        return $this->postingRuleSetRepository->update($ruleSet, $data->attributes, $lines, $userId);
    }

    public function duplicate(int $sourceId, StorePostingRuleSetData $data, int $userId): DuplicatePostingRuleSetResult
    {
        $source = $this->findForEdit($sourceId);

        if ($source === null) {
            throw new NotFoundHttpException('Source posting rule set not found.');
        }

        $this->postingRuleValidationService->assertHasDebitAndCredit($data->lines);

        $attributes = [
            ...$data->attributes,
            'event_type' => $source->event_type,
            'entity_type' => $source->entity_type,
            'currency_code' => $source->currency_code,
            'priority' => isset($data->attributes['priority'])
                ? (int) $data->attributes['priority']
                : (int) ($source->priority ?? 100),
            'status' => $data->attributes['status'] ?? 'active',
            'branch_id' => $data->attributes['branch_id'] ?? null,
            'legal_entity_id' => $data->attributes['legal_entity_id'] ?? null,
        ];

        $lines = array_map(fn ($line) => $line->toArray(), $data->lines);

        $ruleSet = $this->postingRuleSetRepository->create($attributes, $lines, $userId);

        $warnings = [];

        if (($attributes['status'] ?? 'active') === 'active') {
            $warnings = $this->postingRuleValidationService->samePriorityOverlapWarnings(
                eventType: (string) $attributes['event_type'],
                branchId: isset($attributes['branch_id']) ? (int) $attributes['branch_id'] : null,
                effectiveFrom: Carbon::parse($attributes['effective_from']),
                effectiveTo: ! empty($attributes['effective_to'])
                    ? Carbon::parse($attributes['effective_to'])
                    : null,
                priority: (int) $attributes['priority'],
                excludeId: $ruleSet->id,
            );
        }

        return new DuplicatePostingRuleSetResult($ruleSet, $warnings);
    }
}
