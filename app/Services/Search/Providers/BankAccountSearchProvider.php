<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\Accounting\Contracts\AccountingModuleGate;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class BankAccountSearchProvider extends AbstractSearchProvider
{
    public function __construct(
        private readonly AccountingModuleGate $modules,
    ) {}

    public function id(): string
    {
        return 'bank_accounts';
    }

    public function category(): string
    {
        return 'accounting';
    }

    public function icon(): string
    {
        return 'landmark';
    }

    public function priority(): int
    {
        return 65;
    }

    public function permissions(): array
    {
        return ['accounting.manage-bank-accounts', 'accounting.view'];
    }

    public function isAvailable(User $user, BranchContext $context): bool
    {
        if (! parent::isAvailable($user, $context)) {
            return false;
        }

        return in_array('bank_reconciliation', $this->modules->enabledModules($context->branchId), true);
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = BankAccount::query()
            ->where(function ($q) use ($like): void {
                $q->where('bank_name', 'like', $like)
                    ->orWhere('account_title', 'like', $like)
                    ->orWhere('account_number_masked', 'like', $like);
            });

        $this->scopeBranch($builder, $context);

        return $builder->orderBy('bank_name')->limit($limit)->get()->map(function (BankAccount $account): SearchResult {
            return new SearchResult(
                id: 'bank-'.$account->id,
                provider: $this->id(),
                category: $this->category(),
                title: $account->account_title ?: $account->bank_name,
                subtitle: trim(($account->bank_name ?? '').' · '.($account->account_number_masked ?? ''), ' ·'),
                routeName: 'admin.accounting.bank-accounts.index',
                icon: $this->icon(),
                score: 75.0,
            );
        })->all();
    }
}
