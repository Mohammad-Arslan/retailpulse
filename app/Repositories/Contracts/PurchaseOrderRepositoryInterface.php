<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByIdWithRelations(int $id): ?PurchaseOrder;

    public function create(array $attributes): PurchaseOrder;

    public function update(PurchaseOrder $order, array $attributes): PurchaseOrder;
}
