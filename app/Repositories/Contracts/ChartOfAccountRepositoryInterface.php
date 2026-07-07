<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ChartOfAccount;
use Illuminate\Support\Collection;

interface ChartOfAccountRepositoryInterface
{
    /**
     * @return Collection<int, ChartOfAccount>
     */
    public function allOrderedWithBranch(array $filters = []): Collection;

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function parentOptions(): array;

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function postableOptions(): array;

    public function findById(int $id): ?ChartOfAccount;

    public function create(array $attributes): ChartOfAccount;

    public function update(ChartOfAccount $account, array $attributes): ChartOfAccount;
}
