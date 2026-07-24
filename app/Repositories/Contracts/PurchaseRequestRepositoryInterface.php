<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseRequestRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByIdWithRelations(int $id): ?PurchaseRequest;

    public function create(array $attributes): PurchaseRequest;

    public function update(PurchaseRequest $request, array $attributes): PurchaseRequest;
}
