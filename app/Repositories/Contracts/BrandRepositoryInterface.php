<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BrandRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function allActive(): Collection;

    public function findById(int $id): ?Brand;

    public function create(array $attributes): Brand;

    public function update(Brand $brand, array $attributes): Brand;

    public function delete(Brand $brand): void;
}
