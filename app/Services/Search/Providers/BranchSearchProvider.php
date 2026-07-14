<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\Branch;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class BranchSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'branches';
    }

    public function category(): string
    {
        return 'branches';
    }

    public function icon(): string
    {
        return 'building-2';
    }

    public function priority(): int
    {
        return 54;
    }

    public function permissions(): array
    {
        return ['branches.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = Branch::query()
            ->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)->orWhere('code', 'like', $like);
            });

        if ($context->accessibleBranchIds !== null) {
            $builder->whereIn('id', $context->accessibleBranchIds);
        }

        return $builder->orderBy('name')->limit($limit)->get()->map(function (Branch $branch): SearchResult {
            return new SearchResult(
                id: 'branch-'.$branch->id,
                provider: $this->id(),
                category: $this->category(),
                title: $branch->name,
                subtitle: $branch->code,
                routeName: 'admin.branches.edit',
                routeParams: ['branch' => $branch->id],
                icon: $this->icon(),
                score: 70.0,
            );
        })->all();
    }
}
