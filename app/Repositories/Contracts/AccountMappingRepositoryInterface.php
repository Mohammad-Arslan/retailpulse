<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AccountMapping;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccountMappingRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $attributes): AccountMapping;

    public function update(AccountMapping $mapping, array $attributes): AccountMapping;

    public function delete(AccountMapping $mapping): void;
}
