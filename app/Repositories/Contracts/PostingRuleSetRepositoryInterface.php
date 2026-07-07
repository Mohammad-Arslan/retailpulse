<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PostingRuleSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PostingRuleSetRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByIdWithLines(int $id): ?PostingRuleSet;

    public function update(PostingRuleSet $ruleSet, array $attributes, array $lines, int $userId): PostingRuleSet;
}
