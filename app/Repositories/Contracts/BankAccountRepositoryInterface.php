<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\BankAccount;
use App\Models\BankStatementLine;
use Illuminate\Support\Collection;

interface BankAccountRepositoryInterface
{
    /**
     * @return Collection<int, BankAccount>
     */
    public function allWithRelations(): Collection;

    /**
     * @return list<array{id: int, bank_name: string, account_title: string}>
     */
    public function selectOptions(): array;

    public function findById(int $id): ?BankAccount;

    public function firstId(): ?int;

    public function create(array $attributes): BankAccount;

    /**
     * @return Collection<int, BankStatementLine>
     */
    public function recentStatementLines(int $bankAccountId, int $limit = 100): Collection;
}
