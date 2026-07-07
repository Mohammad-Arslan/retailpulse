<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\JournalEntry;
use App\Repositories\Contracts\JournalEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class JournalEntryRepository implements JournalEntryRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = JournalEntry::query()->with(['branch:id,name']);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('journal_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('journal_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('journal_date', '<=', $filters['to']);
        }

        $sort = $filters['sort'] ?? 'journal_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy(
            in_array($sort, ['journal_date', 'journal_number', 'status', 'created_at'], true) ? $sort : 'journal_date',
            $direction,
        );

        return $query->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): ?JournalEntry
    {
        return JournalEntry::query()
            ->with([
                'transactions.account:id,code,name',
                'branch:id,name',
                'fiscalYear:id,name',
                'postedByUser:id,name',
                'reversalOf:id,journal_number',
            ])
            ->find($id);
    }
}
