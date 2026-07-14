<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\Supplier;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class SupplierSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'suppliers';
    }

    public function category(): string
    {
        return 'suppliers';
    }

    public function icon(): string
    {
        return 'user-round';
    }

    public function priority(): int
    {
        return 35;
    }

    public function permissions(): array
    {
        return ['procurement.manage-suppliers', 'procurement.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $suppliers = Supplier::query()
            ->where(function ($q) use ($like, $query): void {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
                if ($this->looksLikeCode($query)) {
                    $q->orWhere('code', 'like', $query.'%');
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $suppliers->map(function (Supplier $supplier): SearchResult {
            return new SearchResult(
                id: 'supplier-'.$supplier->id,
                provider: $this->id(),
                category: $this->category(),
                title: $supplier->name,
                subtitle: $supplier->code ? 'Code: '.$supplier->code : $supplier->phone,
                meta: array_filter(['code' => $supplier->code]),
                routeName: 'admin.suppliers.show',
                routeParams: ['supplier' => $supplier->id],
                icon: $this->icon(),
                score: 80.0,
            );
        })->all();
    }
}
