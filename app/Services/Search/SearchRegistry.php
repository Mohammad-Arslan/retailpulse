<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Services\Search\Contracts\SearchProvider;
use InvalidArgumentException;

final class SearchRegistry
{
    /** @var array<string, SearchProvider> */
    private array $providers = [];

    public function register(SearchProvider $provider): void
    {
        $id = $provider->id();

        if (isset($this->providers[$id])) {
            throw new InvalidArgumentException("Search provider [{$id}] is already registered.");
        }

        $this->providers[$id] = $provider;
    }

    /**
     * @return list<SearchProvider>
     */
    public function all(): array
    {
        $providers = array_values($this->providers);

        usort(
            $providers,
            static fn (SearchProvider $a, SearchProvider $b): int => $a->priority() <=> $b->priority(),
        );

        return $providers;
    }
}
