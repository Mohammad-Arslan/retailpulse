<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PostingRuleLine;
use App\Models\PostingRuleSet;
use App\Repositories\Contracts\PostingRuleSetRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PostingRuleSetRepository implements PostingRuleSetRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PostingRuleSet::query()->withCount('lines');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sort = $filters['sort'] ?? 'priority';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy(
            in_array($sort, ['code', 'name', 'event_type', 'priority', 'effective_from'], true) ? $sort : 'priority',
            $direction,
        );

        return $query->paginate($perPage)->withQueryString();
    }

    public function findByIdWithLines(int $id): ?PostingRuleSet
    {
        return PostingRuleSet::query()
            ->with(['lines.account:id,code,name'])
            ->find($id);
    }

    public function update(PostingRuleSet $ruleSet, array $attributes, array $lines, int $userId): PostingRuleSet
    {
        return DB::transaction(function () use ($ruleSet, $attributes, $lines, $userId) {
            $ruleSet->update([
                ...$attributes,
                'updated_by' => $userId,
            ]);

            $ruleSet->lines()->delete();

            foreach ($lines as $line) {
                PostingRuleLine::query()->create([
                    'posting_rule_set_id' => $ruleSet->id,
                    ...$line,
                ]);
            }

            return $ruleSet->fresh(['lines.account']) ?? $ruleSet;
        });
    }
}
