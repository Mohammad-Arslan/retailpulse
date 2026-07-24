<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PurchaseRequest;
use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PurchaseRequestRepository implements PurchaseRequestRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseRequest::query()
            ->with(['branch', 'warehouse', 'creator'])
            ->withCount('items');

        $sort = $filters['sort'] ?? 'created_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['reference_no', 'total', 'status', 'created_at', 'needed_by'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where('reference_no', 'like', $term);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findByIdWithRelations(int $id): ?PurchaseRequest
    {
        return PurchaseRequest::query()
            ->with([
                'branch',
                'warehouse',
                'items.variant.product',
                'items.preferredSupplier',
                'items.unit',
                'approver',
                'creator',
                'convertedPurchaseOrder',
            ])
            ->find($id);
    }

    public function create(array $attributes): PurchaseRequest
    {
        return PurchaseRequest::query()->create($attributes);
    }

    public function update(PurchaseRequest $request, array $attributes): PurchaseRequest
    {
        $request->update($attributes);

        return $request->fresh(['items.variant', 'branch', 'warehouse']) ?? $request;
    }
}
