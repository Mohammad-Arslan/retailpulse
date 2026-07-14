<?php

declare(strict_types=1);

namespace App\Services\Search\Providers;

use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Services\Search\Support\AbstractSearchProvider;
use App\Support\BranchContext;

final class JournalEntrySearchProvider extends AbstractSearchProvider
{
    public function id(): string
    {
        return 'journals';
    }

    public function category(): string
    {
        return 'accounting';
    }

    public function icon(): string
    {
        return 'scroll-text';
    }

    public function priority(): int
    {
        return 60;
    }

    public function permissions(): array
    {
        return ['accounting.view'];
    }

    public function search(string $query, User $user, BranchContext $context, int $limit): array
    {
        $like = $this->like($query);

        $builder = JournalEntry::query()
            ->where(function ($q) use ($like): void {
                $q->where('journal_number', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('source_number', 'like', $like);
            });

        $this->scopeBranch($builder, $context);

        return $builder->latest('id')->limit($limit)->get()->map(function (JournalEntry $entry): SearchResult {
            return new SearchResult(
                id: 'journal-'.$entry->id,
                provider: $this->id(),
                category: $this->category(),
                title: $entry->journal_number ?? 'Journal #'.$entry->id,
                subtitle: $entry->description ?: $entry->reference,
                meta: ['status' => $entry->status?->value],
                routeName: 'admin.accounting.journal-entries.show',
                routeParams: ['journal_entry' => $entry->id],
                icon: $this->icon(),
                score: 80.0,
            );
        })->all();
    }
}
