<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\Sale;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class SaleSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'sales';
    }

    public function category(): string
    {
        return 'sales';
    }

    public function icon(): string
    {
        return 'receipt';
    }

    public function priority(): int
    {
        return 40;
    }

    public function permissions(): array
    {
        return ['sales.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = Sale::query()
            ->with(['invoice', 'customer:id,name'])
            ->where(function ($q) use ($like, $query): void {
                $q->whereHas('invoice', fn ($iq) => $iq->where('number', 'like', $like))
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', $like));

                if (ctype_digit($query)) {
                    $q->orWhere('id', (int) $query);
                }
            });

        $this->scopeBranch($builder, $context);

        $sales = $builder->latest('id')->limit($limit)->get();

        return $sales->map(function (Sale $sale): SearchResult {
            $number = $sale->invoice?->number ?? '#'.$sale->id;
            $customer = $sale->customer?->name;

            return new SearchResult(
                id: 'sale-'.$sale->id,
                provider: $this->id(),
                category: $this->category(),
                title: $number,
                subtitle: $customer ? 'Customer: '.$customer : (string) $sale->status?->value,
                meta: [
                    'amount' => $sale->grand_total,
                    'status' => $sale->status?->value,
                ],
                routeName: 'admin.sales.show',
                routeParams: ['sale' => $sale->id],
                icon: $this->icon(),
                score: 85.0,
            );
        })->all();
    }
}
