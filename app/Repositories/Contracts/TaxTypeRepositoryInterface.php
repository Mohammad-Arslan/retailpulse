<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TaxType;
use Illuminate\Support\Collection;

interface TaxTypeRepositoryInterface
{
    /**
     * @return Collection<int, TaxType>
     */
    public function allOrdered(): Collection;

    public function create(array $attributes): TaxType;
}
