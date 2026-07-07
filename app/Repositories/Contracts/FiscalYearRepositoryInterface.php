<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\FiscalYear;
use App\Models\FiscalYearReopenRequest;
use Illuminate\Support\Collection;

interface FiscalYearRepositoryInterface
{
    /**
     * @return Collection<int, FiscalYear>
     */
    public function allOrdered(): Collection;

    /**
     * @return list<array{id: int, name: string}>
     */
    public function options(): array;

    public function create(array $attributes): FiscalYear;

    public function update(FiscalYear $fiscalYear, array $attributes): FiscalYear;

    /**
     * @return Collection<int, FiscalYearReopenRequest>
     */
    public function pendingReopenRequests(): Collection;
}
