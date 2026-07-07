<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface JournalEntryRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?JournalEntry;
}
