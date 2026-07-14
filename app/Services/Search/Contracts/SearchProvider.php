<?php

declare(strict_types=1);

namespace App\Services\Search\Contracts;

use App\Models\User;
use App\Support\BranchContext;

interface SearchProvider
{
    public function id(): string;

    /** i18n key under search.categories.* */
    public function category(): string;

    public function icon(): string;

    /** Lower runs first when grouping categories. */
    public function priority(): int;

    /**
     * User needs any of these permissions (empty = always allowed if isAvailable).
     *
     * @return list<string>
     */
    public function permissions(): array;

    public function isAvailable(User $user, BranchContext $context): bool;

    /**
     * @return list<SearchResult>
     */
    public function search(string $query, User $user, BranchContext $context, int $limit): array;
}
