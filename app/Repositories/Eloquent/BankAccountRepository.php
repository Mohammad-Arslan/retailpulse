<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use Illuminate\Support\Collection;

final class BankAccountRepository implements BankAccountRepositoryInterface
{
    public function allWithRelations(): Collection
    {
        return BankAccount::query()
            ->with(['branch:id,name', 'coaAccount:id,code,name', 'currency:id,code,name'])
            ->orderBy('bank_name')
            ->get();
    }

    public function selectOptions(): array
    {
        return BankAccount::query()
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_title'])
            ->map(fn (BankAccount $account) => [
                'id' => $account->id,
                'bank_name' => $account->bank_name,
                'account_title' => $account->account_title,
            ])
            ->values()
            ->all();
    }

    public function findById(int $id): ?BankAccount
    {
        return BankAccount::query()->with('coaAccount')->find($id);
    }

    public function firstId(): ?int
    {
        $id = BankAccount::query()->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function create(array $attributes): BankAccount
    {
        return BankAccount::query()->create($attributes);
    }

    public function recentStatementLines(int $bankAccountId, int $limit = 100): Collection
    {
        return BankStatementLine::query()
            ->where('bank_account_id', $bankAccountId)
            ->orderByDesc('transaction_date')
            ->limit($limit)
            ->get();
    }
}
