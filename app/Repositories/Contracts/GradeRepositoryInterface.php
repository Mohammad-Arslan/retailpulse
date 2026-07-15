<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GradeRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, Grade>
     */
    public function activeForSelect(): Collection;

    /**
     * @return Collection<int, Grade>
     */
    public function findOverlapping(string $code, mixed $legalEntityId, ?string $from, ?string $to, ?int $excludeId = null): Collection;

    public function create(array $attributes): Grade;

    public function update(Grade $grade, array $attributes): Grade;
}
