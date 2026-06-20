<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CountSession;
use App\Repositories\Contracts\CountSessionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class CountSessionRepository implements CountSessionRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return CountSession::query()
            ->with(['warehouse', 'branch', 'creator'])
            ->when(
                $filters['warehouse_id'] ?? null,
                fn ($q, $id) => $q->where('warehouse_id', $id),
            )
            ->when(
                $filters['branch_id'] ?? null,
                fn ($q, $id) => $q->where('branch_id', $id),
            )
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status),
            )
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByIdWithRelations(int $id): ?CountSession
    {
        return CountSession::query()
            ->with([
                'warehouse',
                'branch',
                'creator',
                'approver',
                'lines.variant.product',
                'lines.binLocation',
            ])
            ->find($id);
    }

    public function nextReferenceNo(): string
    {
        $last = CountSession::query()
            ->where('reference_no', 'like', 'CNT-%')
            ->orderByDesc('id')
            ->value('reference_no');

        $sequence = 1;

        if (is_string($last) && preg_match('/CNT-(\d+)/', $last, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('CNT-%06d', $sequence);
    }
}
