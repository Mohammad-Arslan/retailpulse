<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UnitRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function allActive(): Collection;

    public function findById(int $id): ?Unit;

    public function create(array $attributes): Unit;

    public function update(Unit $unit, array $attributes): Unit;

    public function delete(Unit $unit): void;
}
