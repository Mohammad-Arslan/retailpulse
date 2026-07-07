<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CreditNoteRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
