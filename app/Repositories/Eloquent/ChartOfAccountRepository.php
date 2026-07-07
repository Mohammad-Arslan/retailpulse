<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ChartOfAccount;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use Illuminate\Support\Collection;

final class ChartOfAccountRepository implements ChartOfAccountRepositoryInterface
{
    public function allOrderedWithBranch(array $filters = []): Collection
    {
        $accounts = ChartOfAccount::query()
            ->with('branch:id,name')
            ->orderBy('code')
            ->get();

        if (! empty($filters['search'])) {
            $search = strtolower((string) $filters['search']);
            $accounts = $accounts->filter(
                fn (ChartOfAccount $account) => str_contains(strtolower($account->code), $search)
                    || str_contains(strtolower($account->name), $search),
            );
        }

        if (! empty($filters['type'])) {
            $accounts = $accounts->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $accounts = $accounts->where('status', $filters['status']);
        }

        return $accounts->values();
    }

    public function parentOptions(): array
    {
        return ChartOfAccount::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (ChartOfAccount $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->values()
            ->all();
    }

    public function postableOptions(): array
    {
        return ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (ChartOfAccount $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->values()
            ->all();
    }

    public function findById(int $id): ?ChartOfAccount
    {
        return ChartOfAccount::query()->find($id);
    }

    public function create(array $attributes): ChartOfAccount
    {
        return ChartOfAccount::query()->create($attributes);
    }

    public function update(ChartOfAccount $account, array $attributes): ChartOfAccount
    {
        $account->update($attributes);

        return $account->fresh(['branch']) ?? $account;
    }
}
