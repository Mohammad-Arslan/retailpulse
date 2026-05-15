<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\StockTransfer;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class StockTransferRepository implements StockTransferRepositoryInterface
{
    public function findByIdWithRelations(int $id): ?StockTransfer
    {
        return StockTransfer::query()
            ->with([
                'fromWarehouse.branch',
                'toWarehouse.branch',
                'items.variant.product',
                'items.batch',
                'creator',
                'shipper',
                'receiver',
            ])
            ->find($id);
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse'])
            ->withCount('items')
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status),
            )
            ->when(
                $filters['search'] ?? null,
                function ($q, string $search) {
                    $term = '%'.addcslashes($search, '%_\\').'%';
                    $q->where('reference_no', 'like', $term);
                },
            )
            ->when(
                $filters['branch_id'] ?? null,
                fn ($q, $branchId) => $q->where(function ($inner) use ($branchId) {
                    $inner->whereHas('fromWarehouse', fn ($w) => $w->where('branch_id', $branchId))
                        ->orWhereHas('toWarehouse', fn ($w) => $w->where('branch_id', $branchId));
                }),
            )
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): StockTransfer
    {
        return StockTransfer::query()->create($attributes);
    }

    public function update(StockTransfer $transfer, array $attributes): StockTransfer
    {
        $transfer->update($attributes);

        return $transfer->fresh() ?? $transfer;
    }

    public function nextReferenceNo(): string
    {
        $latest = StockTransfer::query()
            ->where('reference_no', 'like', 'TRF-%')
            ->orderByDesc('id')
            ->value('reference_no');

        $sequence = 1;

        if (is_string($latest) && preg_match('/TRF-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return 'TRF-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
