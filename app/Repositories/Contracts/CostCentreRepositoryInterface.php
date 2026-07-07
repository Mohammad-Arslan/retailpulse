<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CostCentre;
use Illuminate\Support\Collection;

interface CostCentreRepositoryInterface
{
    /**
     * @return Collection<int, CostCentre>
     */
    public function allOrderedWithRelations(): Collection;

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function activeOptions(): array;

    public function create(array $attributes): CostCentre;

    public function update(CostCentre $costCentre, array $attributes): CostCentre;

    public function delete(CostCentre $costCentre): void;

    public function hasChildren(CostCentre $costCentre): bool;
}
