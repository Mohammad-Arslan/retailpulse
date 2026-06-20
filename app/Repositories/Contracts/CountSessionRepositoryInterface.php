<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CountSession;
use Illuminate\Pagination\LengthAwarePaginator;

interface CountSessionRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function findByIdWithRelations(int $id): ?CountSession;

    public function nextReferenceNo(): string;
}
