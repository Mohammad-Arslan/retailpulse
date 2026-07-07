<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateChartOfAccountData;
use App\DTOs\Accounting\UpdateChartOfAccountData;
use App\Models\ChartOfAccount;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Support\ChartOfAccountPresenter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ChartOfAccountService
{
    public function __construct(
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatedIndex(array $filters, int $perPage, Request $request): LengthAwarePaginator
    {
        $accounts = $this->chartOfAccountRepository->allOrderedWithBranch($filters);
        $flat = $this->flattenTree($this->buildTree($accounts));
        $page = max(1, (int) $request->input('page', 1));

        $presented = array_map(
            fn (array $row) => ChartOfAccountPresenter::forList($row['account'], $row['depth']),
            $flat,
        );

        return new LengthAwarePaginator(
            collect($presented)->forPage($page, $perPage)->values()->all(),
            count($presented),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function parentOptions(): array
    {
        return $this->chartOfAccountRepository->parentOptions();
    }

    public function create(CreateChartOfAccountData $data, int $userId): ChartOfAccount
    {
        return $this->chartOfAccountRepository->create([
            ...$data->toArray(),
            'parent_id' => $data->parentId,
            'account_level' => $this->resolveAccountLevel($data->parentId),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function update(ChartOfAccount $account, UpdateChartOfAccountData $data, int $userId): ChartOfAccount
    {
        $attributes = $data->attributes;

        if (array_key_exists('parent_id', $attributes)) {
            $parentId = $attributes['parent_id'] !== null ? (int) $attributes['parent_id'] : null;
            $attributes['parent_id'] = $parentId;
            $attributes['account_level'] = $this->resolveAccountLevel($parentId);
        }

        return $this->chartOfAccountRepository->update($account, [
            ...$attributes,
            'updated_by' => $userId,
        ]);
    }

    /**
     * @param  Collection<int, ChartOfAccount>  $accounts
     * @return list<array{account: ChartOfAccount, children: list<mixed>}>
     */
    private function buildTree(Collection $accounts): array
    {
        $byParent = $accounts->groupBy(fn (ChartOfAccount $account) => $account->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            $nodes = [];
            foreach ($byParent->get($parentId, collect()) as $account) {
                $nodes[] = [
                    'account' => $account,
                    'children' => $build($account->id),
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    /**
     * @param  list<array{account: ChartOfAccount, children: list<mixed>}>  $nodes
     * @return list<array{account: ChartOfAccount, depth: int}>
     */
    private function flattenTree(array $nodes, int $depth = 0): array
    {
        $flat = [];
        foreach ($nodes as $node) {
            $flat[] = ['account' => $node['account'], 'depth' => $depth];
            $flat = array_merge($flat, $this->flattenTree($node['children'], $depth + 1));
        }

        return $flat;
    }

    private function resolveAccountLevel(?int $parentId): int
    {
        if ($parentId === null) {
            return 1;
        }

        $parent = $this->chartOfAccountRepository->findById($parentId);

        return ($parent?->account_level ?? 0) + 1;
    }
}
