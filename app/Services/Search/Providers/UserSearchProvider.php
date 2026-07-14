<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class UserSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'users';
    }

    public function category(): string
    {
        return 'users';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function priority(): int
    {
        return 70;
    }

    public function permissions(): array
    {
        return ['users.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $users = User::query()
            ->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $users->map(function (User $match): SearchResult {
            return new SearchResult(
                id: 'user-'.$match->id,
                provider: $this->id(),
                category: $this->category(),
                title: $match->name,
                subtitle: $match->email,
                routeName: 'admin.users.edit',
                routeParams: ['user' => $match->id],
                icon: $this->icon(),
                score: 70.0,
            );
        })->all();
    }
}
