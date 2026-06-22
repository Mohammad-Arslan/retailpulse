<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupplierRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Supplier;

    public function findByCode(string $code): ?Supplier;

    public function create(array $attributes): Supplier;

    public function update(Supplier $supplier, array $attributes): Supplier;

    public function nextCode(): string;
}
