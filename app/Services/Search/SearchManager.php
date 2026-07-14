<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\User;
use App\Services\Search\Contracts\SearchResult;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SearchManager
{
    public function __construct(
        private readonly SearchRegistry $registry,
    ) {}

    /**
     * @return array{
     *     query: string,
     *     groups: list<array{category: string, category_label_key: string, results: list<array<string, mixed>>}>,
     *     meta: array{took_ms: int, provider_count: int}
     * }
     */
    public function search(
        string $query,
        User $user,
        BranchContext $context,
        int $perProviderLimit = 5,
    ): array {
        $started = hrtime(true);
        $normalized = trim($query);

        if (mb_strlen($normalized) < 1) {
            return [
                'query' => $normalized,
                'groups' => [],
                'meta' => [
                    'took_ms' => 0,
                    'provider_count' => 0,
                ],
            ];
        }

        $groupsByCategory = [];
        $categoryPriority = [];
        $ran = 0;

        foreach ($this->registry->all() as $provider) {
            if (! $provider->isAvailable($user, $context)) {
                continue;
            }

            $ran++;

            try {
                $results = $provider->search($normalized, $user, $context, $perProviderLimit);
            } catch (Throwable $e) {
                Log::warning('Search provider failed', [
                    'provider' => $provider->id(),
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            if ($results === []) {
                continue;
            }

            usort(
                $results,
                static function (SearchResult $a, SearchResult $b): int {
                    $score = $b->score <=> $a->score;
                    if ($score !== 0) {
                        return $score;
                    }

                    return strcasecmp($a->title, $b->title);
                },
            );

            $category = $provider->category();
            $categoryPriority[$category] ??= $provider->priority();
            $groupsByCategory[$category] ??= [];

            foreach (array_slice($results, 0, $perProviderLimit) as $result) {
                $groupsByCategory[$category][] = $result->toArray();
            }
        }

        uksort(
            $groupsByCategory,
            static fn (string $a, string $b): int => ($categoryPriority[$a] ?? 999) <=> ($categoryPriority[$b] ?? 999),
        );

        $groups = [];
        foreach ($groupsByCategory as $category => $results) {
            $groups[] = [
                'category' => $category,
                'category_label_key' => $category,
                'results' => array_slice($results, 0, $perProviderLimit * 2),
            ];
        }

        $tookMs = (int) round((hrtime(true) - $started) / 1_000_000);

        return [
            'query' => $normalized,
            'groups' => $groups,
            'meta' => [
                'took_ms' => $tookMs,
                'provider_count' => $ran,
            ],
        ];
    }
}
