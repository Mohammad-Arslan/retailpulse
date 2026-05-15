<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function allActive(): Collection;

    public function findById(int $id): ?Category;

    public function create(array $attributes): Category;

    public function update(Category $category, array $attributes): Category;

    public function delete(Category $category): void;
}
