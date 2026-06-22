<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'branch'])
            ->withCount('items');

        $sort = $filters['sort'] ?? 'created_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['reference_no', 'total', 'status', 'created_at', 'expected_delivery_date'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('reference_no', 'like', $term)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findByIdWithRelations(int $id): ?PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with([
                'supplier',
                'branch',
                'items.variant.product',
                'grns.items.variant',
                'grns.warehouse',
                'supplierInvoices.matchResult',
                'approver',
                'creator',
            ])
            ->find($id);
    }

    public function create(array $attributes): PurchaseOrder
    {
        return PurchaseOrder::query()->create($attributes);
    }

    public function update(PurchaseOrder $order, array $attributes): PurchaseOrder
    {
        $order->update($attributes);

        return $order->fresh(['supplier', 'items.variant']) ?? $order;
    }
}
