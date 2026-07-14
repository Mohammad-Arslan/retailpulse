<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\Customer;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class CustomerSearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'customers';
    }

    public function category(): string
    {
        return 'customers';
    }

    public function icon(): string
    {
        return 'user-round';
    }

    public function priority(): int
    {
        return 30;
    }

    public function permissions(): array
    {
        return ['customers.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $customers = Customer::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('cnic', 'like', $like);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $customers->map(function (Customer $customer): SearchResult {
            $subtitle = $customer->phone ?: $customer->email;

            return new SearchResult(
                id: 'customer-'.$customer->id,
                provider: $this->id(),
                category: $this->category(),
                title: $customer->name,
                subtitle: $subtitle,
                meta: array_filter([
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ]),
                routeName: 'admin.customers.show',
                routeParams: ['customer' => $customer->id],
                icon: $this->icon(),
                score: 80.0,
            );
        })->all();
    }
}
