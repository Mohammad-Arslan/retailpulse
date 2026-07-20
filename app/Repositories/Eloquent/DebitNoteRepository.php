<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DebitNote;
use App\Repositories\Contracts\DebitNoteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class DebitNoteRepository implements DebitNoteRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DebitNote::query()
            ->with(['supplier:id,name', 'branch:id,name'])
            ->orderByDesc('issued_at');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        $sort = $filters['sort'] ?? 'issued_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['issued_at', 'reference_no', 'amount', 'created_at'], true)) {
            $query->orderBy($sort, $direction);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
