<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\AccountingEventStatus;
use App\Models\AccountingEvent;
use App\Repositories\Contracts\AccountingEventRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AccountingEventRepository implements AccountingEventRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AccountingEvent::query()
            ->with(['journalEntry:id,journal_number,status']);

        $status = $filters['status'] ?? AccountingEventStatus::Failed->value;
        if ($status !== 'all') {
            $query->where('processing_status', $status);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'like', "%{$search}%")
                    ->orWhere('source_type', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        $sort = $filters['sort'] ?? 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy(
            in_array($sort, ['created_at', 'event_type', 'retry_count', 'processed_at'], true) ? $sort : 'created_at',
            $direction,
        );

        return $query->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): ?AccountingEvent
    {
        return AccountingEvent::query()->find($id);
    }
}
