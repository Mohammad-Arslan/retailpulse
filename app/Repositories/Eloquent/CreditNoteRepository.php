<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CreditNote;
use App\Repositories\Contracts\CreditNoteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CreditNoteRepository implements CreditNoteRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CreditNote::query()
            ->with(['customer:id,name', 'branch:id,name'])
            ->orderByDesc('date');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('credit_note_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $sort = $filters['sort'] ?? 'date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['date', 'credit_note_number', 'amount', 'created_at'], true)) {
            $query->orderBy($sort, $direction);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
