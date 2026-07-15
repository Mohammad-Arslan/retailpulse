<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Designation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DesignationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, Designation>
     */
    public function activeForSelect(): Collection;

    public function create(array $attributes): Designation;

    public function update(Designation $designation, array $attributes): Designation;
}
