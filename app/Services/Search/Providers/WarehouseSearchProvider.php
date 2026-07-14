<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class WarehouseSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'warehouses';
    }

    public function category(): string
    {
        return 'branches';
    }

    public function icon(): string
    {
        return 'boxes';
    }

    public function priority(): int
    {
        return 55;
    }

    public function permissions(): array
    {
        return ['warehouses.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = Warehouse::query()
            ->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)->orWhere('code', 'like', $like);
            });

        $this->scopeBranch($builder, $context);

        return $builder->orderBy('name')->limit($limit)->get()->map(function (Warehouse $warehouse): SearchResult {
            return new SearchResult(
                id: 'warehouse-'.$warehouse->id,
                provider: $this->id(),
                category: $this->category(),
                title: $warehouse->name,
                subtitle: $warehouse->code,
                routeName: 'admin.warehouses.edit',
                routeParams: ['warehouse' => $warehouse->id],
                icon: $this->icon(),
                score: 70.0,
            );
        })->all();
    }
}
